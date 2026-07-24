(function () {
	'use strict';

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback, { once: true });
		} else {
			callback();
		}
	}

	ready(function () {
		var forms = document.querySelectorAll('.sabri-news-filter');
		forms.forEach(function (form) {
			form.addEventListener('submit', function () {
				var button = form.querySelector('button[type="submit"]');
				if (button) {
					button.setAttribute('aria-busy', 'true');
				}
			});
		});

		var cards = document.querySelectorAll('[data-sabri-global-key]');
		var seen = new Set();
		cards.forEach(function (card) {
			var key = card.getAttribute('data-sabri-global-key');
			if (!key || seen.has(key)) {
				card.remove();
				return;
			}
			seen.add(key);
		});
	});
}());
