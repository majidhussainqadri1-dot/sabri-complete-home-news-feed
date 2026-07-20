(function () {
	'use strict';

	function status(bar, message) {
		var target = bar ? bar.querySelector('[data-sabri-action-status]') : null;
		if (target) {
			target.textContent = message || '';
		}
	}

	function fallbackCopy(text) {
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			var field = document.createElement('textarea');
			field.value = text;
			field.setAttribute('readonly', 'readonly');
			field.style.position = 'fixed';
			field.style.opacity = '0';
			document.body.appendChild(field);
			field.select();
			try {
				if (!document.execCommand('copy')) {
					throw new Error('copy_failed');
				}
				resolve();
			} catch (error) {
				reject(error);
			} finally {
				document.body.removeChild(field);
			}
		});
	}

	function share(button) {
		var bar = button.closest('[data-sabri-interactions]');
		var url = bar ? bar.getAttribute('data-share-url') : '';
		var title = bar ? bar.getAttribute('data-share-title') : '';
		if (!bar || !url || button.disabled) {
			return;
		}

		button.disabled = true;
		bar.setAttribute('aria-busy', 'true');
		status(bar, 'Opening share options');

		var operation;
		if (navigator.share && typeof navigator.share === 'function') {
			operation = navigator.share({ title: title || document.title, url: url });
		} else {
			operation = fallbackCopy(url).then(function () {
				status(bar, 'Link copied.');
			});
		}

		Promise.resolve(operation)
			.then(function () {
				if (navigator.share && typeof navigator.share === 'function') {
					status(bar, 'Shared.');
				}
			})
			.catch(function (error) {
				if (error && error.name === 'AbortError') {
					status(bar, 'Share cancelled.');
					return;
				}
				return fallbackCopy(url)
					.then(function () { status(bar, 'Link copied.'); })
					.catch(function () { status(bar, 'Unable to share or copy this link.'); });
			})
			.then(function () {
				button.disabled = false;
				bar.setAttribute('aria-busy', 'false');
			});
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-sabri-share]');
		if (!button) {
			return;
		}
		event.preventDefault();
		share(button);
	});
}());
