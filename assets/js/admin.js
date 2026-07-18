(function () {
	'use strict';

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (!target || !target.matches('[data-sabri-confirm-toggle]')) {
			return;
		}

		var submit = document.querySelector(target.getAttribute('data-sabri-confirm-toggle'));
		if (submit) {
			submit.disabled = !target.checked;
		}
	});
}());
