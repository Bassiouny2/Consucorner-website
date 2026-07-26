<?php
/**
 * Email + Telegram notifications, recipients, thresholds, templates.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_Notifications
 */
class CCS_Notifications {

	const OPT_RECIPIENTS = 'notifications_recipients';
	const OPT_THRESHOLDS = 'notifications_thresholds';
	const OPT_TEMPLATES  = 'notifications_templates';
	const OPT_TELEGRAM   = 'notifications_telegram';

	/**
	 * Default thresholds.
	 *
	 * @return array<string, int|bool>
	 */
	public static function default_thresholds() {
		return array(
			'brute_force_attempts' => 5,
			'brute_force_minutes'  => 10,
			'rate_limit_requests'  => 100,
			'rate_limit_minutes'   => 1,
			'new_country_alert'    => true,
			'score_drop_below'     => 70,
		);
	}

	/**
	 * Default email templates.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function default_templates() {
		return array(
			'critical_attack' => array(
				'subject' => '[{site_name}] Critical attack blocked from {ip}',
				'body'    => "A critical security event was detected:\n\nEvent: {event}\nIP: {ip}\nTime: {time}\n\nDetails:\n{details}",
			),
			'daily_summary'   => array(
				'subject' => '[{site_name}] Daily security summary',
				'body'    => "Daily security report:\n\n{details}",
			),
			'score_drop'      => array(
				'subject' => '[{site_name}] Security score dropped',
				'body'    => "The security score has dropped:\n\n{details}",
			),
			'new_country'     => array(
				'subject' => '[{site_name}] Traffic from a new country: {details}',
				'body'    => "Suspicious traffic from a country not seen before.\n\nIP: {ip}\nEvent: {event}\nTime: {time}",
			),
		);
	}

	/**
	 * Default Telegram settings.
	 */
	public static function default_telegram() {
		return array(
			'enabled'   => false,
			'bot_token' => '',
			'chat_id'   => '',
			'events'    => array( 'critical_attack' ),
		);
	}

	/**
	 * Get recipients array.
	 *
	 * @return array<int, array{email:string,name:string,types:array<int,string>}>
	 */
	public static function get_recipients() {
		$value = get_option( CCS_OPTION_PREFIX . self::OPT_RECIPIENTS, array() );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	public static function save_recipients( array $recipients ) {
		$clean = array();
		foreach ( $recipients as $r ) {
			$email = isset( $r['email'] ) ? sanitize_email( $r['email'] ) : '';
			if ( ! $email || ! is_email( $email ) ) {
				continue;
			}
			$types_raw = isset( $r['types'] ) && is_array( $r['types'] ) ? $r['types'] : array();
			$types     = array_values( array_intersect(
				array_map( 'sanitize_key', $types_raw ),
				array( 'critical_attack', 'daily_summary', 'weekly_report', 'new_vendor', 'suspicious_order', 'file_change' )
			) );

			$clean[] = array(
				'email' => $email,
				'name'  => isset( $r['name'] ) ? sanitize_text_field( $r['name'] ) : '',
				'types' => $types ? $types : array( 'critical_attack' ),
			);
		}
		update_option( CCS_OPTION_PREFIX . self::OPT_RECIPIENTS, $clean, false );
		return $clean;
	}

	/**
	 * Get thresholds.
	 */
	public static function get_thresholds() {
		$saved = get_option( CCS_OPTION_PREFIX . self::OPT_THRESHOLDS, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_thresholds() );
	}

	public static function save_thresholds( array $t ) {
		$defaults = self::default_thresholds();
		$clean    = array(
			'brute_force_attempts' => max( 1, min( 100, isset( $t['brute_force_attempts'] ) ? (int) $t['brute_force_attempts'] : $defaults['brute_force_attempts'] ) ),
			'brute_force_minutes'  => max( 1, min( 1440, isset( $t['brute_force_minutes'] ) ? (int) $t['brute_force_minutes'] : $defaults['brute_force_minutes'] ) ),
			'rate_limit_requests'  => max( 1, min( 10000, isset( $t['rate_limit_requests'] ) ? (int) $t['rate_limit_requests'] : $defaults['rate_limit_requests'] ) ),
			'rate_limit_minutes'   => max( 1, min( 1440, isset( $t['rate_limit_minutes'] ) ? (int) $t['rate_limit_minutes'] : $defaults['rate_limit_minutes'] ) ),
			'new_country_alert'    => ! empty( $t['new_country_alert'] ),
			'score_drop_below'     => max( 0, min( 100, isset( $t['score_drop_below'] ) ? (int) $t['score_drop_below'] : $defaults['score_drop_below'] ) ),
		);
		update_option( CCS_OPTION_PREFIX . self::OPT_THRESHOLDS, $clean, false );
		return $clean;
	}

	/**
	 * Get email templates.
	 */
	public static function get_templates() {
		$saved = get_option( CCS_OPTION_PREFIX . self::OPT_TEMPLATES, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_templates() );
	}

	public static function save_templates( array $templates ) {
		$defaults = self::default_templates();
		$clean    = array();
		foreach ( $defaults as $key => $tmpl ) {
			$subject = isset( $templates[ $key ]['subject'] ) ? sanitize_text_field( $templates[ $key ]['subject'] ) : $tmpl['subject'];
			$body    = isset( $templates[ $key ]['body'] ) ? wp_kses_post( $templates[ $key ]['body'] ) : $tmpl['body'];
			$clean[ $key ] = array( 'subject' => $subject, 'body' => $body );
		}
		update_option( CCS_OPTION_PREFIX . self::OPT_TEMPLATES, $clean, false );
		return $clean;
	}

	/**
	 * Telegram config.
	 */
	public static function get_telegram() {
		$saved = get_option( CCS_OPTION_PREFIX . self::OPT_TELEGRAM, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_telegram() );
	}

	public static function save_telegram( array $config ) {
		$clean = array(
			'enabled'   => ! empty( $config['enabled'] ),
			'bot_token' => isset( $config['bot_token'] ) ? sanitize_text_field( $config['bot_token'] ) : '',
			'chat_id'   => isset( $config['chat_id'] ) ? sanitize_text_field( $config['chat_id'] ) : '',
			'events'    => array(),
		);
		if ( isset( $config['events'] ) && is_array( $config['events'] ) ) {
			$clean['events'] = array_values( array_intersect(
				array_map( 'sanitize_key', $config['events'] ),
				array_keys( self::default_templates() )
			) );
		}
		update_option( CCS_OPTION_PREFIX . self::OPT_TELEGRAM, $clean, false );
		return $clean;
	}

	/**
	 * Render placeholders in a template.
	 *
	 * @param string               $tmpl    Template body or subject.
	 * @param array<string, mixed> $context Replacement context.
	 * @return string
	 */
	public static function render( $tmpl, array $context ) {
		$replacements = array(
			'{site_name}' => get_bloginfo( 'name' ),
			'{ip}'        => isset( $context['ip'] ) ? (string) $context['ip'] : '',
			'{event}'     => isset( $context['event'] ) ? (string) $context['event'] : '',
			'{time}'      => isset( $context['time'] ) ? (string) $context['time'] : current_time( 'mysql' ),
			'{details}'   => isset( $context['details'] ) ? (string) $context['details'] : '',
		);
		return strtr( $tmpl, $replacements );
	}

	/**
	 * Send an alert by event key. Skips when notifications module is disabled.
	 *
	 * @param string               $event_key One of the template keys.
	 * @param array<string, mixed> $context   Template variables.
	 * @return bool
	 */
	public static function send( $event_key, array $context = array() ) {
		if ( ! CCS_Options::get( 'ccs_log_email_alerts' ) ) {
			return false;
		}

		$templates = self::get_templates();
		if ( ! isset( $templates[ $event_key ] ) ) {
			return false;
		}

		$subject = self::render( $templates[ $event_key ]['subject'], $context );
		$body    = self::render( $templates[ $event_key ]['body'], $context );

		$ok = false;
		foreach ( self::get_recipients() as $r ) {
			if ( ! in_array( $event_key, $r['types'], true ) ) {
				continue;
			}
			$ok = wp_mail( $r['email'], $subject, $body ) || $ok;
		}

		// Telegram fan-out.
		$tg = self::get_telegram();
		if ( ! empty( $tg['enabled'] ) && in_array( $event_key, (array) $tg['events'], true ) && $tg['bot_token'] && $tg['chat_id'] ) {
			self::send_telegram( $tg, $subject . "\n\n" . $body );
		}

		return $ok;
	}

	/**
	 * Low-level Telegram dispatch.
	 *
	 * @param array  $config Telegram config.
	 * @param string $text   Message text.
	 * @return bool
	 */
	public static function send_telegram( array $config, $text ) {
		$url = 'https://api.telegram.org/bot' . rawurlencode( $config['bot_token'] ) . '/sendMessage';
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 5,
				'body'    => array(
					'chat_id' => $config['chat_id'],
					'text'    => $text,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Send a test email to the first recipient (or to the admin email).
	 */
	public static function send_test_email() {
		$recipients = self::get_recipients();
		$to         = $recipients ? $recipients[0]['email'] : get_option( 'admin_email' );
		if ( ! $to ) {
			return false;
		}
		return wp_mail(
			$to,
			'[' . get_bloginfo( 'name' ) . '] ConsucCorner Security — Test Email',
			"This is a test email from ConsucCorner Security. If you received this message, your notifications are wired correctly."
		);
	}

	/**
	 * Test Telegram connectivity.
	 */
	public static function test_telegram() {
		$tg = self::get_telegram();
		if ( empty( $tg['bot_token'] ) || empty( $tg['chat_id'] ) ) {
			return new WP_Error( 'missing_config', __( 'Bot token and chat ID are required.', 'consucorner-security' ) );
		}
		return self::send_telegram( $tg, 'ConsucCorner Security: test message.' );
	}
}
