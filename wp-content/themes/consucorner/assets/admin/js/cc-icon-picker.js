(function () {
	'use strict';

	var config = window.ccIconPicker || {};
	var icons = Array.isArray(config.icons) ? config.icons : [];
	var strings = config.strings || {};

	var modal = document.getElementById('cc-fa-icon-picker');
	if (!modal) {
		return;
	}

	var grid = modal.querySelector('.cc-fa-icon-picker__grid');
	var searchInput = modal.querySelector('.cc-fa-icon-picker__search');
	var emptyState = modal.querySelector('.cc-fa-icon-picker__empty');
	var activeInput = null;
	var gridBuilt = false;
	var lastFocus = null;

	function normalizeIcon(value) {
		value = (value || '').trim();
		if (!value) {
			return '';
		}
		if (/\bfa-(solid|regular|brands|light|thin|duotone)\b/.test(value)) {
			return value.replace(/\s+/g, ' ');
		}
		if (/\bfa-[a-z0-9-]+\b/.test(value)) {
			return ('fa-solid ' + value).replace(/\s+/g, ' ');
		}
		return ('fa-solid fa-' + value.replace(/^fa-/, '')).replace(/\s+/g, ' ');
	}

	function updateFieldPreview(field, value) {
		var preview = field.querySelector('[data-cc-icon-preview]');
		var clearBtn = field.querySelector('[data-cc-icon-clear]');
		var normalized = normalizeIcon(value);

		if (!preview) {
			return;
		}

		preview.innerHTML = '';
		if (normalized) {
			var icon = document.createElement('i');
			icon.className = normalized;
			icon.setAttribute('aria-hidden', 'true');
			preview.appendChild(icon);
			preview.classList.remove('is-empty');
		} else {
			preview.classList.add('is-empty');
		}

		if (clearBtn) {
			clearBtn.hidden = !normalized;
		}
	}

	function markSelected(value) {
		var normalized = normalizeIcon(value);
		grid.querySelectorAll('.cc-fa-icon-picker__item').forEach(function (btn) {
			btn.classList.toggle('is-selected', btn.getAttribute('data-icon-class') === normalized);
		});
	}

	function buildGrid() {
		if (gridBuilt) {
			return;
		}
		gridBuilt = true;

		var fragment = document.createDocumentFragment();
		icons.forEach(function (item) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'cc-fa-icon-picker__item';
			btn.setAttribute('data-icon-class', item.class);
			btn.setAttribute('data-search', (item.terms || item.label || '').toLowerCase());
			btn.setAttribute('role', 'option');
			btn.setAttribute('title', item.class);

			var icon = document.createElement('i');
			icon.className = item.class;
			icon.setAttribute('aria-hidden', 'true');

			var label = document.createElement('span');
			label.className = 'cc-fa-icon-picker__item-label';
			label.textContent = item.label || item.class;

			btn.appendChild(icon);
			btn.appendChild(label);
			fragment.appendChild(btn);
		});

		grid.appendChild(fragment);
	}

	function filterGrid(query) {
		query = (query || '').trim().toLowerCase();
		var visible = 0;

		grid.querySelectorAll('.cc-fa-icon-picker__item').forEach(function (btn) {
			var haystack = btn.getAttribute('data-search') + ' ' + (btn.getAttribute('data-icon-class') || '');
			var show = !query || haystack.indexOf(query) !== -1;
			btn.hidden = !show;
			if (show) {
				visible += 1;
			}
		});

		emptyState.hidden = visible > 0;
	}

	function openModal(input) {
		buildGrid();
		activeInput = input;
		lastFocus = document.activeElement;

		var field = input.closest('[data-cc-icon-field]');
		var currentValue = field ? input.value : '';
		markSelected(currentValue);
		filterGrid('');
		searchInput.value = '';

		modal.hidden = false;
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('cc-fa-icon-picker-open');
		searchInput.focus();
	}

	function closeModal() {
		modal.hidden = true;
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('cc-fa-icon-picker-open');
		activeInput = null;

		if (lastFocus && typeof lastFocus.focus === 'function') {
			lastFocus.focus();
		}
	}

	function selectIcon(iconClass) {
		if (!activeInput) {
			return;
		}

		var field = activeInput.closest('[data-cc-icon-field]');
		activeInput.value = iconClass;
		if (field) {
			updateFieldPreview(field, iconClass);
		}
		closeModal();
	}

	document.addEventListener('click', function (event) {
		var chooseBtn = event.target.closest('[data-cc-icon-choose]');
		if (chooseBtn) {
			event.preventDefault();
			var field = chooseBtn.closest('[data-cc-icon-field]');
			var input = field ? field.querySelector('[data-cc-icon-input]') : null;
			if (input) {
				openModal(input);
			}
			return;
		}

		var clearBtn = event.target.closest('[data-cc-icon-clear]');
		if (clearBtn) {
			event.preventDefault();
			var clearField = clearBtn.closest('[data-cc-icon-field]');
			var clearInput = clearField ? clearField.querySelector('[data-cc-icon-input]') : null;
			if (clearInput) {
				clearInput.value = '';
				updateFieldPreview(clearField, '');
			}
			return;
		}

		var item = event.target.closest('.cc-fa-icon-picker__item');
		if (item && modal.contains(item)) {
			event.preventDefault();
			selectIcon(item.getAttribute('data-icon-class') || '');
			return;
		}

		if (event.target.closest('[data-cc-icon-close]')) {
			event.preventDefault();
			closeModal();
		}
	});

	document.addEventListener('input', function (event) {
		if (event.target.matches('[data-cc-icon-input]')) {
			var field = event.target.closest('[data-cc-icon-field]');
			if (field) {
				updateFieldPreview(field, event.target.value);
			}
		}

		if (event.target === searchInput) {
			filterGrid(event.target.value);
		}
	});

	document.addEventListener('keydown', function (event) {
		if (modal.hidden) {
			return;
		}

		if (event.key === 'Escape') {
			event.preventDefault();
			closeModal();
		}
	});
})();
