(function () {
	'use strict';

	var config = window.SabriHnfNextGeneration || {};
	var endpoint = config.endpoint || '';
	var statusNodes = function () { return document.querySelectorAll('[data-sabri-ng-status]'); };

	function setStatus(message, isError) {
		statusNodes().forEach(function (node) {
			node.textContent = message || '';
			node.setAttribute('data-error', isError ? '1' : '0');
		});
	}

	function requireLogin() {
		if (config.loggedIn) {
			return true;
		}
		setStatus((config.i18n && config.i18n.login) || 'Please sign in.', true);
		if (config.loginUrl) {
			window.location.href = config.loginUrl;
		}
		return false;
	}

	function request(action, payload) {
		if (!requireLogin() || !endpoint) {
			return Promise.reject(new Error('unavailable'));
		}
		var body = Object.assign({}, payload || {}, { action: action });
		return window.fetch(endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || ''
			},
			body: JSON.stringify(body)
		}).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok || !json || json.ok === false) {
					throw new Error((json && json.message) || ((config.i18n && config.i18n.error) || 'Action failed.'));
				}
				return json.data || {};
			});
		});
	}

	function promptText(label) {
		var value = window.prompt(label || 'Enter text');
		return value === null ? null : value.trim();
	}

	function handleAction(button) {
		var action = button.getAttribute('data-sabri-ng-action') || '';
		var postId = parseInt(button.getAttribute('data-post-id') || '0', 10) || 0;
		var payload = {};
		var text;

		if ('quote' === action) {
			text = promptText('Add your quote or context:');
			if (text === null || !text) { return; }
			payload = { post_id: postId, text: text };
		} else if ('repost' === action || 'queue-toggle' === action || 'offline-toggle' === action) {
			payload = { post_id: postId };
		} else if ('qna-question' === action || 'expert-context' === action) {
			text = promptText('Enter your text:');
			if (text === null || !text) { return; }
			payload = { post_id: postId, text: text };
		} else if ('qna-answer' === action) {
			text = promptText('Enter your answer:');
			if (text === null || !text) { return; }
			payload = { post_id: postId, question_id: button.getAttribute('data-question-id') || '', text: text };
		} else if ('follow-topic' === action || 'unfollow-topic' === action) {
			payload = { topic: button.getAttribute('data-topic') || '' };
		} else if ('set-low-bandwidth' === action || 'set-data-saver' === action) {
			payload = { enabled: '1' === button.getAttribute('data-enabled') ? 0 : 1 };
		} else if ('mark-caught-up' === action) {
			payload = {};
		} else {
			return;
		}

		button.disabled = true;
		request(action, payload).then(function () {
			setStatus((config.i18n && config.i18n.saved) || 'Saved.', false);
			if ('set-low-bandwidth' === action || 'set-data-saver' === action) {
				window.location.reload();
			}
		}).catch(function (error) {
			setStatus(error.message || ((config.i18n && config.i18n.error) || 'Action failed.'), true);
		}).finally(function () {
			button.disabled = false;
		});
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-sabri-ng-action]');
		if (!button) { return; }
		event.preventDefault();
		handleAction(button);
	});

	var progressTimer = null;
	document.addEventListener('input', function (event) {
		var input = event.target.closest('[data-sabri-ng-progress]');
		if (!input || !requireLogin()) { return; }
		window.clearTimeout(progressTimer);
		progressTimer = window.setTimeout(function () {
			request('progress', {
				post_id: parseInt(input.getAttribute('data-post-id') || '0', 10) || 0,
				percent: parseInt(input.value || '0', 10) || 0
			}).then(function () {
				setStatus((config.i18n && config.i18n.saved) || 'Saved.', false);
			}).catch(function (error) {
				setStatus(error.message || 'Action failed.', true);
			});
		}, 350);
	});

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('[data-sabri-ng-recipe]');
		if (!form) { return; }
		event.preventDefault();
		var data = new window.FormData(form);
		var recipe = {
			latest: parseInt(data.get('latest') || '0', 10) || 0,
			doctors: parseInt(data.get('doctors') || '0', 10) || 0,
			research: parseInt(data.get('research') || '0', 10) || 0,
			less_personalized: data.get('less_personalized') ? 1 : 0
		};
		request('recipe', { recipe: recipe }).then(function () {
			setStatus((config.i18n && config.i18n.saved) || 'Saved.', false);
		}).catch(function (error) {
			setStatus(error.message || 'Action failed.', true);
		});
	});

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>'"]/g, function (character) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character];
		});
	}

	function exportOfflinePack(control) {
		if (!requireLogin() || !endpoint) { return; }
		var url = endpoint.replace(/\/action(?:\?.*)?$/, '/offline-pack');
		var canDisable = control && 'disabled' in control;
		if (canDisable) { control.disabled = true; }
		window.fetch(url, {
			method: 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce || '' }
		}).then(function (response) { return response.json(); }).then(function (json) {
			if (!json || json.ok === false || !json.data) { throw new Error('Offline pack unavailable.'); }
			var items = json.data.items || [];
			var html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sabri Offline Feed Pack</title></head><body><main><h1>Sabri Offline Feed Pack</h1><p>Generated ' + escapeHtml(json.data.generated_at_utc || '') + '</p>';
			items.forEach(function (item) {
				html += '<article><h2>' + escapeHtml(item.title) + '</h2><p><a href="' + escapeHtml(item.url) + '">Canonical source</a></p>' + (item.content || '') + '</article><hr>';
			});
			html += '</main></body></html>';
			var blob = new window.Blob([html], { type: 'text/html;charset=utf-8' });
			var anchor = document.createElement('a');
			anchor.href = window.URL.createObjectURL(blob);
			anchor.download = 'sabri-offline-feed-pack.html';
			document.body.appendChild(anchor);
			anchor.click();
			window.URL.revokeObjectURL(anchor.href);
			anchor.remove();
			setStatus('Offline pack prepared.', false);
		}).catch(function (error) {
			setStatus(error.message || 'Offline pack unavailable.', true);
		}).finally(function () {
			if (canDisable) { control.disabled = false; }
		});
	}

	/*
	 * The server renders a normal REST link so the feature is discoverable without
	 * JavaScript. Cookie-authenticated REST calls require the WP REST nonce,
	 * therefore enhanced browsers must intercept that link and export through the
	 * authenticated fetch path instead of navigating to a nonce-less endpoint.
	 */
	document.addEventListener('click', function (event) {
		var control = event.target.closest('[data-sabri-ng-offline-export], a[href*="/next-generation/offline-pack"]');
		if (!control) { return; }
		event.preventDefault();
		exportOfflinePack(control);
	});
}());
