(function () {
	'use strict';
	function ready(callback) {
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, { once: true });
		else callback();
	}
	ready(function () {
		document.querySelectorAll('.sabri-news-filter').forEach(function (form) {
			form.addEventListener('submit', function () {
				var button = form.querySelector('button[type="submit"]');
				if (button) button.setAttribute('aria-busy', 'true');
			});
		});
		var seen = new Set();
		document.querySelectorAll('[data-sabri-global-key]').forEach(function (card) {
			var key = card.getAttribute('data-sabri-global-key');
			if (!key || seen.has(key)) { card.remove(); return; }
			seen.add(key);
		});
		document.querySelectorAll('[data-sabri-news-copy-link]').forEach(function (button) {
			button.addEventListener('click', function () {
				var url = button.getAttribute('data-url') || window.location.href;
				var status = button.parentNode ? button.parentNode.querySelector('[data-sabri-news-copy-status]') : null;
				var done = function (message) { if (status) status.textContent = message; };
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(function () { done('Link copied.'); }, function () { done('Copy failed.'); });
				} else {
					done('Copy is not supported. Use the permanent link.');
				}
			});
		});
	});
}());
