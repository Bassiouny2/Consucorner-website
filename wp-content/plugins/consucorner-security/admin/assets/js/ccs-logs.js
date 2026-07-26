/*
 * ConsucCorner Security — Logs page
 * REST-driven table, filters, pagination, auto-refresh, IP drawer.
 */
( function () {
	'use strict';

	if ( typeof window.ccsAdmin === 'undefined' ) {
		return;
	}

	const cfg = window.ccsAdmin;
	const COUNTRY_FLAGS = {
		EG: '🇪🇬', US: '🇺🇸', RU: '🇷🇺', CN: '🇨🇳', IN: '🇮🇳', BR: '🇧🇷',
		DE: '🇩🇪', FR: '🇫🇷', GB: '🇬🇧', SA: '🇸🇦', AE: '🇦🇪', TR: '🇹🇷',
		UA: '🇺🇦', IR: '🇮🇷', VN: '🇻🇳', ID: '🇮🇩', PK: '🇵🇰', NG: '🇳🇬',
	};

	function flagFor( code ) {
		if ( ! code ) return '';
		const up = code.toUpperCase();
		if ( COUNTRY_FLAGS[ up ] ) return COUNTRY_FLAGS[ up ];
		if ( up.length === 2 ) {
			return String.fromCodePoint(
				0x1f1e6 + up.charCodeAt( 0 ) - 65,
				0x1f1e6 + up.charCodeAt( 1 ) - 65
			);
		}
		return '';
	}

	function severityIcon( sev ) {
		if ( 'critical' === sev ) return '<span class="ccs-sev ccs-sev--critical" title="Critical">●</span>';
		if ( 'warning' === sev ) return '<span class="ccs-sev ccs-sev--warning" title="Warning">●</span>';
		return '<span class="ccs-sev ccs-sev--info" title="Info">●</span>';
	}

	function escapeHtml( str ) {
		if ( str === null || str === undefined ) return '';
		return String( str ).replace( /[&<>"']/g, function ( s ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ s ];
		} );
	}

	const state = {
		page: 1,
		per_page: 50,
		total: 0,
		filters: {},
		autoRefresh: false,
		timer: null,
	};

	async function api( path, options ) {
		options = options || {};
		const opts = Object.assign(
			{ headers: { 'X-WP-Nonce': cfg.restNonce, 'Content-Type': 'application/json' } },
			options
		);
		const res = await fetch( cfg.restUrl + path, opts );
		if ( ! res.ok ) {
			throw new Error( res.statusText );
		}
		return res.json();
	}

	function readFilters() {
		const form = document.querySelector( '[data-ccs-logs-filters]' );
		if ( ! form ) return {};
		const out = {};
		[ 'from', 'to', 'severity', 'category', 'country', 'search' ].forEach( ( name ) => {
			const f = form.querySelector( '[name="' + name + '"]' );
			if ( f && f.value ) {
				out[ name === 'category' ? 'category' : name === 'country' ? 'country' : name === 'search' ? 'search' : name ] = f.value;
			}
		} );
		return out;
	}

	function buildQuery( params ) {
		const usp = new URLSearchParams();
		Object.keys( params ).forEach( ( k ) => {
			if ( params[ k ] !== undefined && params[ k ] !== null && params[ k ] !== '' ) {
				usp.append( k, params[ k ] );
			}
		} );
		return usp.toString();
	}

	function renderRows( rows ) {
		const tbody = document.querySelector( '[data-ccs-logs-body]' );
		if ( ! tbody ) return;

		if ( ! rows.length ) {
			tbody.innerHTML = '<tr><td colspan="8" class="ccs-table__empty">No events match your filters.</td></tr>';
			return;
		}

		tbody.innerHTML = rows.map( ( r ) => {
			const flag = flagFor( r.country_code );
			const details = r.details_parsed ? JSON.stringify( r.details_parsed ) : ( r.details || '' );
			return (
				'<tr data-log-id="' + r.id + '">' +
					'<td>' + severityIcon( r.severity ) + '</td>' +
					'<td><span title="' + escapeHtml( r.created_at ) + '">' + escapeHtml( r.time_diff ) + ' ago</span></td>' +
					'<td>' + escapeHtml( r.event_label ) + '</td>' +
					'<td><button type="button" class="ccs-link" data-action="ip-info" data-ip="' + escapeHtml( r.ip_address ) + '">' + escapeHtml( r.ip_address || '—' ) + '</button>' +
						( r.is_blocked ? ' <span class="ccs-pill ccs-pill--danger">blocked</span>' : '' ) +
						( r.is_whitelisted ? ' <span class="ccs-pill ccs-pill--success">whitelist</span>' : '' ) +
					'</td>' +
					'<td>' + flag + ' ' + escapeHtml( r.country_code || '' ) + '</td>' +
					'<td><span title="' + escapeHtml( r.user_agent || '' ) + '">' + escapeHtml( r.user_agent_compact ) + '</span></td>' +
					'<td>' + ( details ? '<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--small" data-action="details" data-payload="' + escapeHtml( details ) + '">View</button>' : '—' ) + '</td>' +
					'<td class="ccs-row-actions">' +
						'<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--small" data-action="block" data-ip="' + escapeHtml( r.ip_address ) + '">Block</button> ' +
						'<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--small" data-action="whitelist" data-ip="' + escapeHtml( r.ip_address ) + '">Whitelist</button>' +
					'</td>' +
				'</tr>'
			);
		} ).join( '' );
	}

	function renderPagination() {
		const info = document.querySelector( '[data-pagination-info]' );
		const prev = document.querySelector( '[data-ccs-pagination] [data-action="prev"]' );
		const next = document.querySelector( '[data-ccs-pagination] [data-action="next"]' );
		const start = state.total === 0 ? 0 : ( state.page - 1 ) * state.per_page + 1;
		const end = Math.min( state.page * state.per_page, state.total );
		if ( info ) info.textContent = start + '–' + end + ' of ' + state.total;
		if ( prev ) prev.disabled = state.page <= 1;
		if ( next ) next.disabled = state.page * state.per_page >= state.total;
	}

	async function loadLogs() {
		const params = Object.assign( {}, state.filters, { page: state.page, per_page: state.per_page } );
		try {
			const data = await api( 'logs?' + buildQuery( params ) );
			state.total = data.total || 0;
			renderRows( data.rows || [] );
			renderPagination();
		} catch ( e ) {
			console.error( 'CCS logs:', e );
		}
	}

	function toggleAutoRefresh( on ) {
		state.autoRefresh = !! on;
		if ( state.timer ) {
			clearInterval( state.timer );
			state.timer = null;
		}
		if ( state.autoRefresh ) {
			state.timer = setInterval( loadLogs, 30000 );
		}
	}

	async function openDrawer( ip ) {
		const drawer = document.querySelector( '[data-ccs-ip-drawer]' );
		const title = drawer.querySelector( '[data-drawer-title]' );
		const body = drawer.querySelector( '[data-drawer-body]' );
		drawer.classList.add( 'is-open' );
		drawer.setAttribute( 'aria-hidden', 'false' );
		title.textContent = ip;
		body.innerHTML = '<p>Loading IP info…</p>';

		try {
			const info = await api( 'ip/' + encodeURIComponent( ip ) + '/info' );
			body.innerHTML = renderDrawerBody( info );
		} catch ( e ) {
			body.innerHTML = '<p class="ccs-error">Failed to load IP details.</p>';
		}
	}

	function renderDrawerBody( info ) {
		const geo = info.geo || {};
		const act = info.activity || {};
		const recent = ( info.recent_events || [] ).map( ( r ) => (
			'<li>' + severityIcon( r.severity ) + ' <strong>' + escapeHtml( r.event_label ) + '</strong> · <span>' + escapeHtml( r.time_diff ) + ' ago</span></li>'
		) ).join( '' );

		return (
			'<section class="ccs-drawer-section">' +
				'<h3>IP Info</h3>' +
				'<dl class="ccs-dl">' +
					'<dt>IP</dt><dd><code>' + escapeHtml( info.ip ) + '</code></dd>' +
					'<dt>Country</dt><dd>' + flagFor( geo.country_code ) + ' ' + escapeHtml( geo.country || '—' ) + '</dd>' +
					'<dt>City</dt><dd>' + escapeHtml( geo.city || '—' ) + '</dd>' +
					'<dt>ISP</dt><dd>' + escapeHtml( geo.isp || '—' ) + '</dd>' +
					'<dt>Type</dt><dd>' + escapeHtml( geo.type || '—' ) + '</dd>' +
					'<dt>Threat score</dt><dd><strong>' + ( info.threat_score || 0 ) + '/100</strong></dd>' +
					'<dt>First seen</dt><dd>' + escapeHtml( act.first_seen || '—' ) + '</dd>' +
					'<dt>Last seen</dt><dd>' + escapeHtml( act.last_seen || '—' ) + '</dd>' +
				'</dl>' +
			'</section>' +
			'<section class="ccs-drawer-section">' +
				'<h3>Activity Summary</h3>' +
				'<div class="ccs-drawer-stats">' +
					'<div><strong>' + ( act.total || 0 ) + '</strong><span>Total events</span></div>' +
					'<div><strong>' + ( act.blocked || 0 ) + '</strong><span>Blocked</span></div>' +
					'<div><strong>' + ( act.logins || 0 ) + '</strong><span>Successful logins</span></div>' +
				'</div>' +
			'</section>' +
			'<section class="ccs-drawer-section">' +
				'<h3>Recent Events</h3>' +
				'<ul class="ccs-drawer-events">' + ( recent || '<li>No recent events.</li>' ) + '</ul>' +
			'</section>' +
			'<section class="ccs-drawer-section ccs-drawer-actions">' +
				'<h3>Actions</h3>' +
				'<button type="button" class="ccs-btn ccs-btn--danger" data-drawer-action="block-permanent" data-ip="' + escapeHtml( info.ip ) + '">🚫 Block Permanently</button>' +
				'<button type="button" class="ccs-btn ccs-btn--ghost" data-drawer-action="block" data-duration="24h" data-ip="' + escapeHtml( info.ip ) + '">⏱ Block 24h</button>' +
				'<button type="button" class="ccs-btn ccs-btn--ghost" data-drawer-action="block" data-duration="7d" data-ip="' + escapeHtml( info.ip ) + '">⏱ Block 7d</button>' +
				'<button type="button" class="ccs-btn ccs-btn--ghost" data-drawer-action="block" data-duration="30d" data-ip="' + escapeHtml( info.ip ) + '">⏱ Block 30d</button>' +
				'<button type="button" class="ccs-btn ccs-btn--primary" data-drawer-action="whitelist" data-ip="' + escapeHtml( info.ip ) + '">✅ Whitelist</button>' +
				'<button type="button" class="ccs-btn ccs-btn--ghost" data-drawer-action="copy" data-ip="' + escapeHtml( info.ip ) + '">📋 Copy IP</button>' +
				'<a class="ccs-btn ccs-btn--ghost" target="_blank" rel="noopener" href="https://www.abuseipdb.com/check/' + encodeURIComponent( info.ip ) + '">🔍 Lookup on AbuseIPDB</a>' +
			'</section>'
		);
	}

	function closeDrawer() {
		const drawer = document.querySelector( '[data-ccs-ip-drawer]' );
		if ( drawer ) {
			drawer.classList.remove( 'is-open' );
			drawer.setAttribute( 'aria-hidden', 'true' );
		}
	}

	function openDetailsModal( payload ) {
		const modal = document.querySelector( '[data-ccs-details-modal]' );
		const body = modal.querySelector( '[data-modal-body]' );
		try {
			const obj = JSON.parse( payload );
			body.textContent = JSON.stringify( obj, null, 2 );
		} catch ( e ) {
			body.textContent = payload;
		}
		modal.hidden = false;
	}

	function bind() {
		const form = document.querySelector( '[data-ccs-logs-filters]' );
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				state.page = 1;
				state.filters = readFilters();
				loadLogs();
			} );
			form.addEventListener( 'reset', function () {
				setTimeout( function () {
					state.page = 1;
					state.filters = {};
					loadLogs();
				}, 0 );
			} );
			const exportButton = form.querySelector( '[data-action="export-csv"]' );
			if ( exportButton ) {
				exportButton.addEventListener( 'click', function () {
					const params = Object.assign( {}, state.filters, { _wpnonce: cfg.restNonce } );
					const url = cfg.restUrl + 'logs/export?' + buildQuery( params );
					window.open( url, '_blank' );
				} );
			}
		}

		document.addEventListener( 'click', async function ( e ) {
			const target = e.target.closest( '[data-action]' );
			if ( ! target ) return;
			const action = target.getAttribute( 'data-action' );

			if ( 'refresh' === action ) {
				loadLogs();
				return;
			}
			if ( 'autorefresh' === action ) {
				toggleAutoRefresh( target.checked );
				return;
			}
			if ( 'prev' === action ) {
				if ( state.page > 1 ) { state.page--; loadLogs(); }
				return;
			}
			if ( 'next' === action ) {
				state.page++; loadLogs();
				return;
			}
			if ( 'ip-info' === action ) {
				e.preventDefault();
				openDrawer( target.getAttribute( 'data-ip' ) );
				return;
			}
			if ( 'details' === action ) {
				openDetailsModal( target.getAttribute( 'data-payload' ) );
				return;
			}
			if ( 'close-drawer' === action ) {
				closeDrawer();
				return;
			}
			if ( 'close-modal' === action ) {
				document.querySelector( '[data-ccs-details-modal]' ).hidden = true;
				return;
			}
			if ( 'block' === action ) {
				const ip = target.getAttribute( 'data-ip' );
				if ( ! ip ) return;
				try {
					await api( 'ip/block', { method: 'POST', body: JSON.stringify( { ip: ip, reason: 'Blocked from logs view', duration: '7d' } ) } );
					loadLogs();
				} catch ( err ) { alert( cfg.i18n.error ); }
				return;
			}
			if ( 'whitelist' === action ) {
				const ip = target.getAttribute( 'data-ip' );
				if ( ! ip ) return;
				try {
					await api( 'ip/whitelist', { method: 'POST', body: JSON.stringify( { ip: ip, label: 'Added from logs view' } ) } );
					loadLogs();
				} catch ( err ) { alert( cfg.i18n.error ); }
			}
		} );

		document.addEventListener( 'click', async function ( e ) {
			const target = e.target.closest( '[data-drawer-action]' );
			if ( ! target ) return;
			const action = target.getAttribute( 'data-drawer-action' );
			const ip = target.getAttribute( 'data-ip' );

			if ( 'copy' === action ) {
				navigator.clipboard.writeText( ip );
				target.textContent = '✓ Copied';
				return;
			}
			if ( 'block-permanent' === action ) {
				await api( 'ip/block', { method: 'POST', body: JSON.stringify( { ip: ip, reason: 'Manual block', duration: 'permanent' } ) } );
				openDrawer( ip ); loadLogs();
				return;
			}
			if ( 'block' === action ) {
				await api( 'ip/block', { method: 'POST', body: JSON.stringify( { ip: ip, reason: 'Manual block', duration: target.getAttribute( 'data-duration' ) } ) } );
				openDrawer( ip ); loadLogs();
				return;
			}
			if ( 'whitelist' === action ) {
				await api( 'ip/whitelist', { method: 'POST', body: JSON.stringify( { ip: ip, label: 'Whitelisted from drawer' } ) } );
				openDrawer( ip ); loadLogs();
			}
		} );

		// Live widget polling.
		const widget = document.querySelector( '[data-ccs-live-widget]' );
		if ( widget ) {
			setInterval( async function () {
				try {
					const feed = await api( 'live-feed' );
					const t = feed.totals;
					const summary = widget.querySelector( '[data-live-summary]' );
					const breakdown = widget.querySelector( '[data-live-breakdown]' );
					if ( summary ) summary.textContent = 'Last hour: ' + t.blocked + ' attack' + ( t.blocked === 1 ? '' : 's' ) + ' blocked';
					if ( breakdown ) breakdown.textContent = t.bot_blocked + ' bots · ' + t.brute_force + ' brute-force · ' + t.firewall + ' firewall';
					widget.classList.toggle( 'is-alert', t.blocked > 0 );
				} catch ( e ) { /* silent */ }
			}, 60000 );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const modal = document.querySelector( '[data-ccs-details-modal]' );
		if ( modal ) {
			modal.hidden = true;
		}
		bind();
		loadLogs();
	} );
} )();
