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

	function updateConfirmations(form) {
		var select = form.querySelector('[data-sabri-feed-type]');
		var type = select ? select.value : '';
		var needsMedical = form.getAttribute('data-require-medical-disclaimer') === '1' && [
			'clinical-case',
			'research',
			'public-health'
		].indexOf(type) !== -1;
		var needsPatient = form.getAttribute('data-require-patient-consent') === '1' && type === 'clinical-case';

		[
			{ selector: '[data-sabri-medical-confirmation]', active: needsMedical },
			{ selector: '[data-sabri-patient-confirmation]', active: needsPatient }
		].forEach(function (item) {
			var wrapper = form.querySelector(item.selector);
			var input = wrapper ? wrapper.querySelector('input') : null;
			if (!wrapper || !input) {
				return;
			}
			wrapper.hidden = !item.active;
			input.disabled = !item.active;
			input.required = item.active;
		});
	}

	ready(function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-sabri-composer]'), function (form) {
			updatePanels(form);
			updateConfirmations(form);
			var select = form.querySelector('[data-sabri-feed-type]');
			if (select) {
				select.addEventListener('change', function () {
					updatePanels(form);
					updateConfirmations(form);
				});
			}
		});
	});
}());
