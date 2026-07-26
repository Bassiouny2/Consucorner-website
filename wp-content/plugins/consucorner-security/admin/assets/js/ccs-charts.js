/*
 * ConsucCorner Security — Analytics charts
 * Uses Chart.js v4 (UMD) loaded by WP enqueue.
 */
( function () {
	'use strict';

	if ( typeof window.ccsAdmin === 'undefined' || typeof window.Chart === 'undefined' ) {
		return;
	}

	const cfg = window.ccsAdmin;
	const colors = {
		critical: '#dc2626',
		warning:  '#f59e0b',
		info:     '#2597e0',
		primary:  '#13c9bc',
		ink:      '#0f2740',
		muted:    '#5f7a90',
	};

	const charts = {};
	let currentRange = '7d';

	async function api( path ) {
		const res = await fetch( cfg.restUrl + path, { headers: { 'X-WP-Nonce': cfg.restNonce } } );
		if ( ! res.ok ) throw new Error( res.statusText );
		return res.json();
	}

	function destroyAll() {
		Object.keys( charts ).forEach( ( k ) => {
			if ( charts[ k ] && charts[ k ].destroy ) charts[ k ].destroy();
			delete charts[ k ];
		} );
	}

	async function loadTimeline() {
		const data = await api( 'stats/timeline?range=' + currentRange );
		const ctx = document.querySelector( '[data-chart="timeline"]' );
		if ( ! ctx ) return;
		charts.timeline = new Chart( ctx, {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [
					{ label: 'Critical', data: data.series.critical, borderColor: colors.critical, backgroundColor: colors.critical + '22', tension: 0.3, fill: true },
					{ label: 'Warning',  data: data.series.warning,  borderColor: colors.warning,  backgroundColor: colors.warning + '22',  tension: 0.3, fill: true },
					{ label: 'Info',     data: data.series.info,     borderColor: colors.info,     backgroundColor: colors.info + '22',     tension: 0.3, fill: true },
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: { legend: { position: 'bottom' } },
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		} );
	}

	const TYPE_LABELS = {
		bot_blocked: 'Bot Blocked',
		bot_allowed_google: 'Googlebot',
		scraper_blocked: 'Scraper',
		brute_force_attempt: 'Brute Force',
		login_failed: 'Login Failed',
		login_blocked: 'Login Blocked',
		sql_injection_attempt: 'SQL Injection',
		xss_attempt: 'XSS',
		file_upload_blocked: 'File Upload',
		rate_limit_triggered: 'Rate Limit',
		ddos_attempt: 'DDoS',
		api_abuse: 'API Abuse',
		suspicious_db_query: 'Suspicious DB',
		file_changed: 'File Changed',
	};

	function donutColors( n ) {
		const palette = [ '#13c9bc', '#2597e0', '#f59e0b', '#dc2626', '#7c3aed', '#10b981', '#ec4899', '#0ea5e9', '#f97316', '#84cc16' ];
		const out = [];
		for ( let i = 0; i < n; i++ ) out.push( palette[ i % palette.length ] );
		return out;
	}

	async function loadTypes() {
		const data = await api( 'stats/types?range=' + currentRange );
		const ctx = document.querySelector( '[data-chart="types"]' );
		if ( ! ctx ) return;
		const labels = data.map( ( d ) => TYPE_LABELS[ d.event_type ] || d.event_type );
		const counts = data.map( ( d ) => d.count );
		const bg = donutColors( labels.length );
		const total = counts.reduce( ( a, b ) => a + b, 0 ) || 1;

		charts.types = new Chart( ctx, {
			type: 'doughnut',
			data: { labels, datasets: [ { data: counts, backgroundColor: bg, borderWidth: 0 } ] },
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
		} );

		const legend = document.querySelector( '[data-chart-legend="types"]' );
		if ( legend ) {
			legend.innerHTML = labels.map( ( l, i ) => (
				'<li><span class="ccs-legend-swatch" style="background:' + bg[ i ] + '"></span>' +
				'<span class="ccs-legend-label">' + l + '</span>' +
				'<span class="ccs-legend-value">' + counts[ i ] + ' · ' + Math.round( ( counts[ i ] / total ) * 100 ) + '%</span></li>'
			) ).join( '' );
		}
	}

	async function loadCountries() {
		const data = await api( 'stats/countries?range=' + currentRange );
		const ctx = document.querySelector( '[data-chart="countries"]' );
		if ( ! ctx ) return;
		const labels = data.map( ( d ) => d.country_code );
		const counts = data.map( ( d ) => d.count );
		const bg = labels.map( ( c ) => ( 'EG' === c ? colors.primary : colors.critical ) );

		charts.countries = new Chart( ctx, {
			type: 'bar',
			data: { labels, datasets: [ { data: counts, backgroundColor: bg, borderRadius: 6 } ] },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				indexAxis: 'y',
				plugins: { legend: { display: false } },
				scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		} );
	}

	async function loadHeatmap() {
		const data = await api( 'stats/heatmap?range=' + currentRange );
		const target = document.querySelector( '[data-chart="heatmap"]' );
		if ( ! target ) return;

		const days = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
		const max = data.max || 1;
		let html = '<div class="ccs-heatmap__inner"><div class="ccs-heatmap__hours"><span></span>';
		for ( let h = 0; h < 24; h++ ) html += '<span>' + h + '</span>';
		html += '</div>';
		for ( let d = 0; d < 7; d++ ) {
			html += '<div class="ccs-heatmap__row"><span class="ccs-heatmap__label">' + days[ d ] + '</span>';
			for ( let h = 0; h < 24; h++ ) {
				const n = ( data.grid[ d ] && data.grid[ d ][ h ] ) || 0;
				const alpha = n === 0 ? 0 : 0.15 + ( n / max ) * 0.85;
				html += '<span class="ccs-heatmap__cell" title="' + days[ d ] + ' ' + h + ':00 · ' + n + '" style="background:rgba(220,38,38,' + alpha.toFixed( 2 ) + ')"></span>';
			}
			html += '</div>';
		}
		html += '</div>';
		target.innerHTML = html;
	}

	function escapeHtml( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	async function loadTopIps() {
		const data = await api( 'stats/top-ips?range=' + currentRange );
		const tbody = document.querySelector( '[data-chart="top-ips"]' );
		if ( ! tbody ) return;
		if ( ! data.length ) {
			tbody.innerHTML = '<tr><td colspan="7" class="ccs-table__empty">No attacking IPs in this period.</td></tr>';
			return;
		}
		tbody.innerHTML = data.map( ( r ) => (
			'<tr>' +
				'<td><code>' + escapeHtml( r.ip_address ) + '</code></td>' +
				'<td>' + escapeHtml( r.country_code || '—' ) + '</td>' +
				'<td>' + r.attempts + '</td>' +
				'<td><span class="ccs-minibar"><span style="width:' + r.bar_pct + '%"></span></span></td>' +
				'<td>' + escapeHtml( r.last_seen ) + '</td>' +
				'<td>' + ( r.blocked ? '<span class="ccs-pill ccs-pill--danger">blocked</span>' : '<span class="ccs-pill">active</span>' ) + '</td>' +
				'<td>' + ( r.blocked
					? '<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--small" data-charts-action="unblock" data-ip="' + escapeHtml( r.ip_address ) + '">Unblock</button>'
					: '<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--small" data-charts-action="block" data-ip="' + escapeHtml( r.ip_address ) + '">Block</button>'
				) + '</td>' +
			'</tr>'
		) ).join( '' );
	}

	async function loadScore() {
		const data = await api( 'stats/score' );
		const ctx = document.querySelector( '[data-chart="score"]' );
		if ( ! ctx || ! data.history ) return;
		charts.score = new Chart( ctx, {
			type: 'line',
			data: {
				labels: data.history.labels,
				datasets: [ {
					label: 'Score',
					data: data.history.series,
					borderColor: colors.primary,
					backgroundColor: colors.primary + '33',
					tension: 0.3,
					fill: true,
					spanGaps: true,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { y: { min: 0, max: 100 } },
			},
		} );
	}

	async function loadAll() {
		destroyAll();
		await Promise.all( [ loadTimeline(), loadTypes(), loadCountries(), loadHeatmap(), loadTopIps(), loadScore() ] );
	}

	function bind() {
		const range = document.querySelector( '[data-ccs-range]' );
		if ( range ) {
			range.addEventListener( 'click', function ( e ) {
				const btn = e.target.closest( 'button[data-range]' );
				if ( ! btn ) return;
				range.querySelectorAll( 'button' ).forEach( ( b ) => b.classList.remove( 'is-active' ) );
				btn.classList.add( 'is-active' );
				currentRange = btn.getAttribute( 'data-range' );
				loadAll();
			} );
		}

		document.addEventListener( 'click', async function ( e ) {
			const t = e.target.closest( '[data-charts-action]' );
			if ( ! t ) return;
			const ip = t.getAttribute( 'data-ip' );
			const action = t.getAttribute( 'data-charts-action' );
			try {
				if ( 'block' === action ) {
					await fetch( cfg.restUrl + 'ip/block', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce }, body: JSON.stringify( { ip: ip, reason: 'Blocked from analytics', duration: '7d' } ) } );
				} else if ( 'unblock' === action ) {
					await fetch( cfg.restUrl + 'ip/block/' + encodeURIComponent( ip ), { method: 'DELETE', headers: { 'X-WP-Nonce': cfg.restNonce } } );
				}
				loadTopIps();
			} catch ( err ) { /* silent */ }
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		bind();
		loadAll();
	} );
} )();
