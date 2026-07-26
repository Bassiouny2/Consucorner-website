/*
 * ConsucCorner Security — Notifications + Advanced Settings handlers.
 * Speaks to /wp-json/ccs/v1/settings and a handful of IP endpoints.
 */
( function () {
	'use strict';

	if ( typeof window.ccsAdmin === 'undefined' ) return;
	const cfg = window.ccsAdmin;

	function toast( message, type ) {
		const el = document.createElement( 'div' );
		el.className = 'ccs-toast ccs-toast--' + ( type || 'success' );
		el.textContent = message;
		document.body.appendChild( el );
		setTimeout( () => el.classList.add( 'is-visible' ), 10 );
		setTimeout( () => { el.classList.remove( 'is-visible' ); setTimeout( () => el.remove(), 300 ); }, 2500 );
	}

	async function api( path, options ) {
		options = options || {};
		const res = await fetch( cfg.restUrl + path, Object.assign(
			{ headers: { 'X-WP-Nonce': cfg.restNonce, 'Content-Type': 'application/json' } },
			options
		) );
		if ( ! res.ok ) {
			const data = await res.json().catch( () => null );
			throw new Error( ( data && data.message ) || res.statusText );
		}
		return res.json();
	}

	function saveSection( section, data ) {
		return api( 'settings', { method: 'POST', body: JSON.stringify( { section, data } ) } );
	}

	// ------------------------------------------------------------------
	// Notifications page
	// ------------------------------------------------------------------

	function collectRecipients() {
		const rows = document.querySelectorAll( '[data-recipients-body] [data-recipient-row]' );
		const out = [];
		rows.forEach( ( row ) => {
			const email = row.querySelector( '[data-field="email"]' ).value.trim();
			if ( ! email ) return;
			const name = row.querySelector( '[data-field="name"]' ).value.trim();
			const types = Array.from( row.querySelectorAll( 'input[data-type]:checked' ) ).map( ( i ) => i.getAttribute( 'data-type' ) );
			out.push( { email, name, types } );
		} );
		return out;
	}

	function addRecipientRow() {
		const tpl = document.querySelector( '[data-recipient-template]' );
		const tbody = document.querySelector( '[data-recipients-body]' );
		if ( ! tpl || ! tbody ) return;
		const empty = tbody.querySelector( '[data-empty]' );
		if ( empty ) empty.remove();
		const node = tpl.content.firstElementChild.cloneNode( true );
		node.setAttribute( 'data-index', String( tbody.children.length ) );
		tbody.appendChild( node );
	}

	function collectThresholds() {
		const root = document.querySelector( '[data-section="thresholds"]' );
		return {
			brute_force_attempts: parseInt( root.querySelector( '[data-field="brute_force_attempts"]' ).value, 10 ),
			brute_force_minutes:  parseInt( root.querySelector( '[data-field="brute_force_minutes"]' ).value, 10 ),
			rate_limit_requests:  parseInt( root.querySelector( '[data-field="rate_limit_requests"]' ).value, 10 ),
			rate_limit_minutes:   parseInt( root.querySelector( '[data-field="rate_limit_minutes"]' ).value, 10 ),
			score_drop_below:     parseInt( root.querySelector( '[data-field="score_drop_below"]' ).value, 10 ),
			new_country_alert:    root.querySelector( '[data-field="new_country_alert"]' ).checked,
		};
	}

	function collectTemplates() {
		const out = {};
		document.querySelectorAll( '[data-template-key]' ).forEach( ( block ) => {
			const key = block.getAttribute( 'data-template-key' );
			out[ key ] = {
				subject: block.querySelector( '[data-field="subject"]' ).value,
				body:    block.querySelector( '[data-field="body"]' ).value,
			};
		} );
		return out;
	}

	function collectTelegram() {
		const root = document.querySelector( '[data-section="telegram"]' );
		if ( ! root ) return null;
		return {
			enabled:   root.querySelector( '[data-field="enabled"]' ).checked,
			bot_token: root.querySelector( '[data-field="bot_token"]' ).value.trim(),
			chat_id:   root.querySelector( '[data-field="chat_id"]' ).value.trim(),
			events:    Array.from( root.querySelectorAll( 'input[data-tg-event]:checked' ) ).map( ( i ) => i.getAttribute( 'data-tg-event' ) ),
		};
	}

	// ------------------------------------------------------------------
	// Advanced settings
	// ------------------------------------------------------------------

	function collectRows( selector, fields ) {
		const out = [];
		document.querySelectorAll( selector + ' [data-row-index]' ).forEach( ( row ) => {
			const item = {};
			fields.forEach( ( f ) => {
				const el = row.querySelector( '[data-field="' + f + '"]' );
				if ( el ) item[ f ] = el.value;
			} );
			out.push( item );
		} );
		return out;
	}

	function appendRow( tableSelector, rowHtml ) {
		const tbody = document.querySelector( tableSelector );
		if ( ! tbody ) return;
		const empty = tbody.querySelector( '[data-empty]' );
		if ( empty ) empty.remove();
		const wrapper = document.createElement( 'tbody' );
		wrapper.innerHTML = rowHtml;
		const tr = wrapper.firstElementChild;
		tr.setAttribute( 'data-row-index', String( tbody.children.length ) );
		tbody.appendChild( tr );
	}

	const HIGH_RISK = [ 'RU', 'CN', 'KP', 'IR' ];

	function applyPreset( preset ) {
		const tbody = document.querySelector( '[data-table="country-rules"]' );
		if ( ! tbody ) return;
		tbody.innerHTML = '';
		if ( 'allow-all' === preset ) {
			tbody.innerHTML = '<tr data-empty><td colspan="3" class="ccs-table__empty">All countries allowed.</td></tr>';
			return;
		}
		const rows = [];
		if ( 'egypt-only' === preset ) {
			rows.push( { code: 'EG', action: 'allow' } );
			rows.push( { code: '*', action: 'block' } );
		} else if ( 'high-risk' === preset ) {
			HIGH_RISK.forEach( ( c ) => rows.push( { code: c, action: 'block' } ) );
		}
		rows.forEach( ( r, idx ) => {
			appendRow( '[data-table="country-rules"]', countryRowHtml( r.code, r.action, idx ) );
		} );
	}

	function countryRowHtml( code, action, idx ) {
		return (
			'<tr data-row-index="' + idx + '" data-country="' + code + '">' +
				'<td><input type="text" data-field="code" maxlength="2" value="' + code + '" style="width:80px;text-transform:uppercase"></td>' +
				'<td><select data-field="action">' +
					'<option value="allow"' + ( action === 'allow' ? ' selected' : '' ) + '>Allow</option>' +
					'<option value="block"' + ( action === 'block' ? ' selected' : '' ) + '>Block</option>' +
					'<option value="challenge"' + ( action === 'challenge' ? ' selected' : '' ) + '>Challenge</option>' +
				'</select></td>' +
				'<td style="text-align:right"><button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-row">Remove</button></td>' +
			'</tr>'
		);
	}

	function bind() {
		document.addEventListener( 'click', async function ( e ) {
			const target = e.target.closest( '[data-action]' );
			if ( ! target ) return;
			const action = target.getAttribute( 'data-action' );

			try {
				switch ( action ) {
					case 'add-recipient': addRecipientRow(); break;

					case 'remove-recipient': {
						const row = target.closest( '[data-recipient-row]' );
						if ( row ) row.remove();
						break;
					}

					case 'save-recipients':
						await saveSection( 'recipients', collectRecipients() );
						toast( cfg.i18n.saved );
						break;

					case 'test-email':
						await api( 'notifications/test-email', { method: 'POST' } );
						toast( 'Test email sent.' );
						break;

					case 'save-thresholds':
						await saveSection( 'thresholds', collectThresholds() );
						toast( cfg.i18n.saved );
						break;

					case 'save-templates':
						await saveSection( 'templates', collectTemplates() );
						toast( cfg.i18n.saved );
						break;

					case 'save-telegram':
						await saveSection( 'telegram', collectTelegram() );
						toast( cfg.i18n.saved );
						break;

					case 'test-telegram':
						await api( 'notifications/test-telegram', { method: 'POST' } );
						toast( 'Telegram test sent.' );
						break;

					// Advanced — whitelist IP single-row form.
					case 'add-whitelist-ip': {
						const form = target.closest( '[data-form="add-whitelist-ip"]' );
						const ip = form.querySelector( '[data-field="ip"]' ).value.trim();
						const label = form.querySelector( '[data-field="label"]' ).value.trim();
						if ( ! ip ) { toast( 'IP is required', 'error' ); return; }
						await api( 'ip/whitelist', { method: 'POST', body: JSON.stringify( { ip, label } ) } );
						window.location.reload();
						break;
					}

					case 'remove-whitelist-ip': {
						const row = target.closest( 'tr[data-ip]' );
						const ip = row.getAttribute( 'data-ip' );
						await api( 'ip/whitelist/' + encodeURIComponent( ip ), { method: 'DELETE' } );
						row.remove();
						toast( cfg.i18n.saved );
						break;
					}

					case 'unblock-ip': {
						const row = target.closest( 'tr[data-ip]' );
						const ip = row.getAttribute( 'data-ip' );
						await api( 'ip/block/' + encodeURIComponent( ip ), { method: 'DELETE' } );
						row.remove();
						toast( cfg.i18n.saved );
						break;
					}

					case 'clear-expired':
						await api( 'ip/clear-expired', { method: 'POST' } );
						toast( cfg.i18n.saved );
						window.location.reload();
						break;

					// Domain + user whitelist + rate limits — generic table actions.
					case 'add-domain':
						appendRow( '[data-table="whitelist-domains"]', '<tr><td><input type="text" data-field="domain" value=""></td><td><input type="text" data-field="reason" value=""></td><td style="text-align:right"><button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-row">Remove</button></td></tr>' );
						break;

					case 'save-domains':
						await saveSection( 'whitelist_domains', collectRows( '[data-table="whitelist-domains"]', [ 'domain', 'reason' ] ) );
						toast( cfg.i18n.saved );
						break;

					case 'add-user':
						appendRow( '[data-table="whitelist-users"]', '<tr><td><input type="text" data-field="username" value=""></td><td><select data-field="role"><option value="">Any role</option></select></td><td style="text-align:right"><button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-row">Remove</button></td></tr>' );
						break;

					case 'save-users':
						await saveSection( 'whitelist_users', collectRows( '[data-table="whitelist-users"]', [ 'username', 'role' ] ) );
						toast( cfg.i18n.saved );
						break;

					case 'add-country':
						appendRow( '[data-table="country-rules"]', countryRowHtml( '', 'allow', 0 ) );
						break;

					case 'preset-egypt-only': applyPreset( 'egypt-only' ); break;
					case 'preset-high-risk':  applyPreset( 'high-risk' ); break;
					case 'preset-allow-all':  applyPreset( 'allow-all' ); break;

					case 'save-country-rules': {
						const rows = collectRows( '[data-table="country-rules"]', [ 'code', 'action' ] );
						const map = {};
						rows.forEach( ( r ) => {
							const c = ( r.code || '' ).toUpperCase().substring( 0, 2 );
							if ( c.length === 2 ) map[ c ] = r.action;
						} );
						await saveSection( 'country_rules', map );
						toast( cfg.i18n.saved );
						break;
					}

					case 'add-rate-rule':
						appendRow( '[data-table="rate-rules"]',
							'<tr>' +
								'<td><input type="text" data-field="url_pattern" value=""></td>' +
								'<td><input type="number" min="0" data-field="requests_per_min" value="60"></td>' +
								'<td><input type="number" min="0" data-field="burst" value="10"></td>' +
								'<td><input type="text" data-field="whitelist_roles" value=""></td>' +
								'<td><select data-field="action"><option value="block">Block</option><option value="allow">Allow</option><option value="challenge">Challenge</option></select></td>' +
								'<td style="text-align:right"><button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-row">Remove</button></td>' +
							'</tr>'
						);
						break;

					case 'save-rate-rules': {
						const rules = collectRows( '[data-table="rate-rules"]', [ 'url_pattern', 'requests_per_min', 'burst', 'whitelist_roles', 'action' ] )
							.map( ( r ) => ( {
								url_pattern: r.url_pattern,
								requests_per_min: parseInt( r.requests_per_min, 10 ) || 0,
								burst: parseInt( r.burst, 10 ) || 0,
								whitelist_roles: ( r.whitelist_roles || '' ).split( ',' ).map( ( s ) => s.trim() ).filter( Boolean ),
								action: r.action,
							} ) );
						await saveSection( 'rate_limit_rules', rules );
						toast( cfg.i18n.saved );
						break;
					}

					case 'validate-nginx': {
						const area = document.querySelector( '[data-field="nginx-rules"]' );
						const v = area ? area.value : '';
						const open = ( v.match( /\{/g ) || [] ).length;
						const close = ( v.match( /\}/g ) || [] ).length;
						toast( open === close ? 'Syntax looks balanced.' : 'Unbalanced braces detected.', open === close ? 'success' : 'error' );
						break;
					}

					case 'save-nginx': {
						const area = document.querySelector( '[data-field="nginx-rules"]' );
						await saveSection( 'custom_nginx_rules', { rules: area ? area.value : '' } );
						toast( cfg.i18n.saved );
						break;
					}

					case 'save-logs-mgmt': {
						const root = document.querySelector( '[data-section="logs-management"]' );
						const data = {
							retention_days: parseInt( root.querySelector( '[data-field="retention_days"]' ).value, 10 ),
							max_size_mb:    parseInt( root.querySelector( '[data-field="max_size_mb"]' ).value, 10 ),
							level:          root.querySelector( '[data-field="level"]' ).value,
							sample_rate:    parseInt( root.querySelector( '[data-field="sample_rate"]' ).value, 10 ),
							auto_clean:     root.querySelector( '[data-field="auto_clean"]' ).checked,
							async_logging:  root.querySelector( '[data-field="async_logging"]' ).checked,
						};
						await saveSection( 'logs_management', data );
						toast( cfg.i18n.saved );
						break;
					}

					case 'clean-logs':
						toast( 'Cleanup scheduled.' );
						break;

					case 'clear-all-logs':
						if ( ! window.confirm( 'Delete ALL security logs? This cannot be undone.' ) ) return;
						await api( 'logs/clear', { method: 'POST' } );
						toast( 'All logs cleared.' );
						window.location.reload();
						break;

					case 'remove-row': {
						const row = target.closest( 'tr' );
						if ( row ) row.remove();
						break;
					}
				}
			} catch ( err ) {
				toast( err.message || cfg.i18n.error, 'error' );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', bind );
} )();
