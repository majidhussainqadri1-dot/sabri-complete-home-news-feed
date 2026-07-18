(function () {
	'use strict';

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}
		callback();
	}

	function uniqueAppend(list, html) {
		var template = document.createElement('template');
		template.innerHTML = html;
		Array.prototype.forEach.call(template.content.children, function (node) {
			if (!node.id || !document.getElementById(node.id)) {
				list.appendChild(node);
			}
		});
	}

	ready(function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-sabri-load-more]'), function (button) {
			button.addEventListener('click', function () {
				var feed = button.closest('.sabri-hnf-feed');
				var list = feed ? feed.querySelector('[data-sabri-feed-list]') : null;
				var status = feed ? feed.querySelector('.sabri-hnf-feed__status') : null;
				var nextUrl = button.getAttribute('data-next-url');
				var stateUrl = button.getAttribute('data-state-url');
				if (!list || !nextUrl || button.disabled) {
					return;
				}

				button.disabled = true;
				if (status) {
					status.textContent = 'Loading';
				}

				window.fetch(nextUrl, { credentials: 'same-origin' })
					.then(function (response) {
						if (!response.ok) {
							throw new Error('load_failed');
						}
						return response.json();
					})
					.then(function (payload) {
						var data = payload && payload.data ? payload.data : payload;
						uniqueAppend(list, data.html || '');
						if (data.has_more && data.next_page) {
							var url = new URL(nextUrl, window.location.href);
							url.searchParams.set('sabri_feed_page', data.next_page);
							url.searchParams.set('page', data.next_page);
							button.setAttribute('data-next-url', url.toString());
							button.setAttribute('data-next-page', data.next_page);
							if (stateUrl) {
								var pageUrl = new URL(stateUrl, window.location.href);
								pageUrl.searchParams.set('sabri_feed_page', data.next_page);
								button.setAttribute('data-state-url', pageUrl.toString());
							}
							button.disabled = false;
						} else {
							button.disabled = true;
						}
						if (status) {
							status.textContent = '';
						}
						if (window.history && window.history.replaceState) {
							window.history.replaceState({}, '', stateUrl || window.location.href);
						}
					})
					.catch(function () {
						button.disabled = false;
						if (status) {
							status.textContent = 'Unable to load more posts';
						}
					});
			});
		});
	});
}());
