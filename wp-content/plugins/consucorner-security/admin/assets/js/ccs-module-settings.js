/*
 * ConsucCorner Security — module configuration pages.
 */
( function () {
	'use strict';

	if ( typeof window.ccsAdmin === 'undefined' ) {
		return;
	}

	var cfg = window.ccsAdmin;

	function qs( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function qsa( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function showToast( message, isError ) {
		var toast = qs( '[data-ccs-toast]' );
		if ( ! toast ) {
			return;
		}
		toast.textContent = message;
		toast.hidden = false;
		toast.classList.toggle( 'ccs-toast--error', !! isError );
		window.clearTimeout( showToast._timer );
		showToast._timer = window.setTimeout( function () {
			toast.hidden = true;
		}, 3000 );
	}

	function collectSettings( root ) {
		var data = {};

		qsa( '[data-config-field]', root ).forEach( function ( field ) {
			var key = field.getAttribute( 'data-config-field' );
			if ( ! key ) {
				return;
			}
			if ( field.type === 'checkbox' ) {
				data[ key ] = field.checked;
			} else {
				data[ key ] = field.value;
			}
		} );

		qsa( '[data-config-array]', root ).forEach( function ( group ) {
			var key = group.getAttribute( 'data-config-array' );
			data[ key ] = qsa( 'input[type="checkbox"]:checked', group ).map( function ( input ) {
				return input.value;
			} );
		} );

		return data;
	}

	function saveModuleSettings( root ) {
		var moduleId = root.getAttribute( 'data-module-id' );
		var body = new URLSearchParams();

		body.set( 'action', 'ccs_save_module_settings' );
		body.set( 'nonce', cfg.nonce );
		body.set( 'module_id', moduleId );
		body.set( 'settings', JSON.stringify( collectSettings( root ) ) );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || cfg.i18n.error );
				}
				return json.data;
			} );
	}

	function renderMiniChart() {
		var canvas = qs( '[data-ccs-module-chart]' );
		if ( ! canvas || typeof window.Chart === 'undefined' || ! cfg.restUrl ) {
			return;
		}

		fetch( cfg.restUrl + 'stats/timeline?range=7d', {
			headers: { 'X-WP-Nonce': cfg.restNonce },
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				if ( ! data || ! data.labels || ! data.series ) {
					return;
				}
				new window.Chart( canvas, {
					type: 'line',
					data: {
						labels: data.labels,
						datasets: [
							{
								label: 'Critical',
								data: data.series.critical || [],
								borderColor: '#dc2626',
								backgroundColor: 'rgba(220,38,38,.12)',
								tension: 0.35,
								fill: true,
							},
							{
								label: 'Warning',
								data: data.series.warning || [],
								borderColor: '#f59e0b',
								backgroundColor: 'rgba(245,158,11,.12)',
								tension: 0.35,
								fill: true,
							},
							{
								label: 'Info',
								data: data.series.info || [],
								borderColor: '#2597e0',
								backgroundColor: 'rgba(37,151,224,.12)',
								tension: 0.35,
								fill: true,
							},
						],
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: { position: 'bottom' },
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: { precision: 0 },
							},
						},
					},
				} );
			} )
			.catch( function () {} );
	}

	function bind() {
		qsa( '[data-ccs-save-module-settings]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var root = button.closest( '[data-ccs-module-config]' );
				if ( ! root ) {
					return;
				}

				button.disabled = true;
				button.textContent = 'Saving...';

				saveModuleSettings( root )
					.then( function () {
						showToast( cfg.i18n.saved, false );
					} )
					.catch( function ( err ) {
						showToast( err.message || cfg.i18n.error, true );
					} )
					.finally( function () {
						button.disabled = false;
						button.textContent = 'Save Configuration';
					} );
			} );
		} );

		renderMiniChart();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bind );
	} else {
		bind();
	}
} )();
