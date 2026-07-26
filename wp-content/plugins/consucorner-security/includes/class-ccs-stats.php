<?php
/**
 * Pre-aggregated stats for charts and dashboard widgets.
 *
 * All public methods return data structures ready for Chart.js or the
 * REST endpoints, with their heavy SQL cached in 5-minute transients.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_Stats
 */
class CCS_Stats {

	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Cache wrapper.
	 */
	private static function cached( $key, callable $builder ) {
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		$value = $builder();
		set_transient( $key, $value, self::CACHE_TTL );
		return $value;
	}

	/**
	 * Resolve a range string into a [from, to, days] tuple.
	 *
	 * @param string $range 24h|7d|30d|90d.
	 * @return array{from:string,to:string,days:int}
	 */
	public static function resolve_range( $range ) {
		$range = is_string( $range ) ? strtolower( $range ) : '7d';
		switch ( $range ) {
			case '24h':
				$days = 1;
				break;
			case '30d':
				$days = 30;
				break;
			case '90d':
				$days = 90;
				break;
			case '7d':
			default:
				$days = 7;
				break;
		}

		$to   = gmdate( 'Y-m-d H:i:s' );
		$from = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		return array(
			'from' => $from,
			'to'   => $to,
			'days' => $days,
		);
	}

	/**
	 * Top-card numbers for the dashboard / logs page.
	 *
	 * @return array<string, mixed>
	 */
	public static function summary() {
		return self::cached( 'ccs_stats_summary', function () {
			global $wpdb;
			$table = CCS_Logger::table_name();
			$start = gmdate( 'Y-m-d 00:00:00' );
			$h24   = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

			$today_events  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $start ) ); // phpcs:ignore WordPress.DB
			$blocked_24h   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND severity IN ('warning','critical')", $h24 ) ); // phpcs:ignore WordPress.DB
			$unique_ips    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT ip_address) FROM {$table} WHERE created_at >= %s AND ip_address <> ''", $h24 ) ); // phpcs:ignore WordPress.DB
			$top_threat    = $wpdb->get_var( $wpdb->prepare( "SELECT event_type FROM {$table} WHERE created_at >= %s AND severity IN ('warning','critical') GROUP BY event_type ORDER BY COUNT(*) DESC LIMIT 1", $start ) ); // phpcs:ignore WordPress.DB

			return array(
				'today_events' => $today_events,
				'blocked_24h'  => $blocked_24h,
				'unique_ips'   => $unique_ips,
				'top_threat'   => $top_threat ? (string) $top_threat : '',
			);
		} );
	}

	/**
	 * Timeline of events by severity for the given range.
	 *
	 * @param string $range Range slug.
	 * @return array<string, mixed>
	 */
	public static function timeline( $range = '7d' ) {
		$range_data = self::resolve_range( $range );

		return self::cached(
			'ccs_stats_timeline_' . $range,
			function () use ( $range_data ) {
				global $wpdb;
				$table = CCS_Logger::table_name();
				$days  = $range_data['days'];

				$bucket = ( $days <= 1 ) ? 'hour' : 'day';

				if ( 'hour' === $bucket ) {
					$select = "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS bucket";
				} else {
					$select = "DATE_FORMAT(created_at, '%Y-%m-%d') AS bucket";
				}

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT {$select}, severity, COUNT(*) AS n
						 FROM {$table}
						 WHERE created_at >= %s AND created_at <= %s
						 GROUP BY bucket, severity
						 ORDER BY bucket ASC",
						$range_data['from'],
						$range_data['to']
					),
					ARRAY_A
				); // phpcs:ignore WordPress.DB

				$series = array(
					'critical' => array(),
					'warning'  => array(),
					'info'     => array(),
				);
				$labels = array();

				$step = ( 'hour' === $bucket ) ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
				$from = strtotime( $range_data['from'] );
				$to   = strtotime( $range_data['to'] );

				$keyed = array();
				for ( $t = $from; $t <= $to; $t += $step ) {
					$label = ( 'hour' === $bucket ) ? gmdate( 'Y-m-d H:00', $t ) : gmdate( 'Y-m-d', $t );
					$labels[]        = $label;
					$keyed[ $label ] = array( 'critical' => 0, 'warning' => 0, 'info' => 0 );
				}

				foreach ( $rows as $r ) {
					$key = ( 'hour' === $bucket ) ? substr( $r['bucket'], 0, 13 ) . ':00' : $r['bucket'];
					$sev = isset( $r['severity'] ) ? $r['severity'] : 'info';
					if ( isset( $keyed[ $key ][ $sev ] ) ) {
						$keyed[ $key ][ $sev ] += (int) $r['n'];
					}
				}

				foreach ( $keyed as $bucket_row ) {
					$series['critical'][] = $bucket_row['critical'];
					$series['warning'][]  = $bucket_row['warning'];
					$series['info'][]     = $bucket_row['info'];
				}

				return array(
					'labels' => $labels,
					'series' => $series,
				);
			}
		);
	}

	/**
	 * Event-type distribution (donut chart).
	 */
	public static function types( $range = '7d' ) {
		$range_data = self::resolve_range( $range );

		return self::cached(
			'ccs_stats_types_' . $range,
			function () use ( $range_data ) {
				global $wpdb;
				$table = CCS_Logger::table_name();

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT event_type, COUNT(*) AS n
						 FROM {$table}
						 WHERE created_at >= %s
						 GROUP BY event_type
						 ORDER BY n DESC",
						$range_data['from']
					),
					ARRAY_A
				); // phpcs:ignore WordPress.DB

				return array_map(
					static function ( $row ) {
						return array(
							'event_type' => (string) $row['event_type'],
							'count'      => (int) $row['n'],
						);
					},
					$rows ? $rows : array()
				);
			}
		);
	}

	/**
	 * Top attacking countries (bar chart).
	 */
	public static function countries( $range = '7d' ) {
		$range_data = self::resolve_range( $range );

		return self::cached(
			'ccs_stats_countries_' . $range,
			function () use ( $range_data ) {
				global $wpdb;
				$table = CCS_Logger::table_name();

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT country_code, COUNT(*) AS n
						 FROM {$table}
						 WHERE created_at >= %s AND severity IN ('warning','critical') AND country_code IS NOT NULL AND country_code <> ''
						 GROUP BY country_code
						 ORDER BY n DESC
						 LIMIT 10",
						$range_data['from']
					),
					ARRAY_A
				); // phpcs:ignore WordPress.DB

				return array_map(
					static function ( $row ) {
						return array(
							'country_code' => strtoupper( (string) $row['country_code'] ),
							'count'        => (int) $row['n'],
						);
					},
					$rows ? $rows : array()
				);
			}
		);
	}

	/**
	 * 24×7 heatmap grid (Sun..Sat × 0..23).
	 *
	 * @return array{grid:array,max:int}
	 */
	public static function heatmap( $range = '30d' ) {
		$range_data = self::resolve_range( $range );

		return self::cached(
			'ccs_stats_heatmap_' . $range,
			function () use ( $range_data ) {
				global $wpdb;
				$table = CCS_Logger::table_name();

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS n
						 FROM {$table}
						 WHERE created_at >= %s
						 GROUP BY dow, hr",
						$range_data['from']
					),
					ARRAY_A
				); // phpcs:ignore WordPress.DB

				$grid = array();
				for ( $d = 0; $d < 7; $d++ ) {
					$grid[ $d ] = array_fill( 0, 24, 0 );
				}

				$max = 0;
				foreach ( $rows as $r ) {
					$dow = ( (int) $r['dow'] ) - 1; // MySQL DAYOFWEEK: 1=Sun..7=Sat → 0..6
					$hr  = (int) $r['hr'];
					$n   = (int) $r['n'];
					if ( isset( $grid[ $dow ][ $hr ] ) ) {
						$grid[ $dow ][ $hr ] = $n;
						if ( $n > $max ) {
							$max = $n;
						}
					}
				}

				return array(
					'grid' => $grid,
					'max'  => $max,
				);
			}
		);
	}

	/**
	 * Top 20 attacking IPs with mini-bar data.
	 */
	public static function top_ips( $range = '7d' ) {
		$range_data = self::resolve_range( $range );

		return self::cached(
			'ccs_stats_top_ips_' . $range,
			function () use ( $range_data ) {
				global $wpdb;
				$table = CCS_Logger::table_name();
				$blocked_table = CCS_IP_Manager::table_blocked();

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT l.ip_address, l.country_code,
								COUNT(*) AS attempts,
								MAX(l.created_at) AS last_seen,
								(SELECT id FROM {$blocked_table} b WHERE b.ip_address = l.ip_address LIMIT 1) AS is_blocked
						 FROM {$table} l
						 WHERE l.created_at >= %s AND l.ip_address <> '' AND l.severity IN ('warning','critical')
						 GROUP BY l.ip_address
						 ORDER BY attempts DESC
						 LIMIT 20",
						$range_data['from']
					),
					ARRAY_A
				); // phpcs:ignore WordPress.DB

				if ( ! $rows ) {
					return array();
				}

				$max = max( array_map( static function ( $r ) { return (int) $r['attempts']; }, $rows ) );
				$max = max( 1, $max );

				return array_map(
					static function ( $r ) use ( $max ) {
						return array(
							'ip_address'   => (string) $r['ip_address'],
							'country_code' => (string) $r['country_code'],
							'attempts'     => (int) $r['attempts'],
							'last_seen'    => (string) $r['last_seen'],
							'blocked'      => ! empty( $r['is_blocked'] ),
							'bar_pct'      => (int) round( ( (int) $r['attempts'] / $max ) * 100 ),
						);
					},
					$rows
				);
			}
		);
	}

	/**
	 * Security-score history (last 30 days).
	 */
	public static function score_history() {
		$raw = get_option( CCS_OPTION_PREFIX . 'score_history', array() );
		if ( ! is_array( $raw ) ) {
			return array( 'labels' => array(), 'series' => array() );
		}

		$days  = 30;
		$today = strtotime( gmdate( 'Y-m-d' ) );

		$labels = array();
		$series = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$key      = gmdate( 'Y-m-d', $today - ( $i * DAY_IN_SECONDS ) );
			$labels[] = $key;
			$series[] = isset( $raw[ $key ] ) ? (int) $raw[ $key ] : null;
		}

		return array( 'labels' => $labels, 'series' => $series );
	}

	/**
	 * Last-hour micro summary for the live widget.
	 */
	public static function live_feed() {
		global $wpdb;
		$table = CCS_Logger::table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		$counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, COUNT(*) AS n
				 FROM {$table}
				 WHERE created_at >= %s
				 GROUP BY event_type",
				$since
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB

		$totals = array(
			'blocked'     => 0,
			'bot_blocked' => 0,
			'brute_force' => 0,
			'firewall'    => 0,
		);

		foreach ( $counts ? $counts : array() as $row ) {
			$type = (string) $row['event_type'];
			$n    = (int) $row['n'];

			if ( in_array( $type, array( 'bot_blocked', 'scraper_blocked' ), true ) ) {
				$totals['bot_blocked'] += $n;
				$totals['blocked']     += $n;
			} elseif ( in_array( $type, array( 'brute_force_attempt', 'login_blocked' ), true ) ) {
				$totals['brute_force'] += $n;
				$totals['blocked']     += $n;
			} elseif ( in_array( $type, array( 'sql_injection_attempt', 'xss_attempt', 'file_upload_blocked', 'ddos_attempt', 'rate_limit_triggered' ), true ) ) {
				$totals['firewall'] += $n;
				$totals['blocked']  += $n;
			}
		}

		$recent = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, event_type, severity, ip_address, country_code, created_at
				 FROM {$table}
				 WHERE created_at >= %s
				 ORDER BY created_at DESC, id DESC
				 LIMIT 10",
				$since
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB

		return array(
			'totals' => $totals,
			'recent' => $recent ? $recent : array(),
			'pulse'  => (float) get_transient( 'ccs_live_feed_pulse' ),
		);
	}

	/**
	 * Bust every stats cache. Call after any large state change
	 * (manual log import, mass IP unblock, etc.).
	 */
	public static function flush_caches() {
		$keys = array( 'summary', 'timeline_24h', 'timeline_7d', 'timeline_30d', 'timeline_90d', 'types_24h', 'types_7d', 'types_30d', 'types_90d', 'countries_24h', 'countries_7d', 'countries_30d', 'countries_90d', 'heatmap_24h', 'heatmap_7d', 'heatmap_30d', 'heatmap_90d', 'top_ips_24h', 'top_ips_7d', 'top_ips_30d', 'top_ips_90d' );
		foreach ( $keys as $k ) {
			delete_transient( 'ccs_stats_' . $k );
		}
	}
}
