(function () {
	'use strict';

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}
		callback();
	}

	function updatePanels(form) {
		var select = form.querySelector('[data-sabri-feed-type]');
		var value = select ? select.value : '';
		Array.prototype.forEach.call(form.querySelectorAll('[data-sabri-type-panel]'), function (panel) {
			var active = panel.getAttribute('data-sabri-type-panel') === value;
			panel.hidden = !active;
			Array.prototype.forEach.call(panel.querySelectorAll('input, select, textarea'), function (field) {
				field.disabled = !active;
			});
		});
	}

	ready(function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-sabri-composer]'), function (form) {
			updatePanels(form);
			var select = form.querySelector('[data-sabri-feed-type]');
			if (select) {
				select.addEventListener('change', function () {
					updatePanels(form);
				});
			}
		});
	});
}());
