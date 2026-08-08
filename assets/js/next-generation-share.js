(function () {
	'use strict';

	function status(message, isError) {
		var nodes = document.querySelectorAll('[data-sabri-ng-status]');
		nodes.forEach(function (node) {
			node.textContent = message || '';
			node.setAttribute('data-error', isError ? '1' : '0');
		});
	}

	function textFromPayload(payload) {
		var parts = [];
		if (payload.title) { parts.push(payload.title); }
		if (payload.excerpt) { parts.push(payload.excerpt); }
		if (payload.evidence && payload.evidence.level) { parts.push('Evidence: ' + payload.evidence.level); }
		if (payload.warning && payload.warning.warn && payload.warning.message) { parts.push('Note: ' + payload.warning.message); }
		if (payload.url) { parts.push(payload.url); }
		return parts.join('\n\n');
	}

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var area = document.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', 'readonly');
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild(area);
			area.select();
			try {
				document.execCommand('copy');
				resolve();
			} catch (error) {
				reject(error);
			} finally {
				area.remove();
			}
		});
	}

	function sharePayload(payload) {
		var text = textFromPayload(payload);
		if (navigator.share) {
			return navigator.share({
				title: payload.title || 'Sabri knowledge card',
				text: text,
				url: payload.url || undefined
			}).then(function () {
				status('Knowledge card shared.', false);
			}).catch(function (error) {
				if (error && 'AbortError' === error.name) { return; }
				return copyText(text).then(function () { status('Knowledge card copied.', false); });
			});
		}
		return copyText(text).then(function () { status('Knowledge card copied.', false); });
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('a[href*="/next-generation/share-card/"]');
		if (!link) { return; }
		event.preventDefault();
		link.setAttribute('aria-busy', 'true');
		window.fetch(link.href, { method: 'GET', credentials: 'same-origin' })
			.then(function (response) {
				return response.json().then(function (json) {
					if (!response.ok || !json || json.ok === false || !json.data) {
						throw new Error((json && json.message) || 'Knowledge card is unavailable.');
					}
					return json.data;
				});
			})
			.then(sharePayload)
			.catch(function (error) { status(error.message || 'Knowledge card is unavailable.', true); })
			.finally(function () { link.removeAttribute('aria-busy'); });
	});
}());
