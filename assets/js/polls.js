(function () {
	'use strict';

	function closestPoll(node) {
		return node && node.closest ? node.closest('[data-sabri-poll]') : null;
	}

	function status(poll, message) {
		var target = poll ? poll.querySelector('[data-poll-status]') : null;
		if (target) {
			target.textContent = message || '';
		}
	}

	function setBusy(poll, busy) {
		if (!poll) {
			return;
		}
		poll.setAttribute('aria-busy', busy ? 'true' : 'false');
		Array.prototype.forEach.call(poll.querySelectorAll('button, input'), function (control) {
			control.disabled = !!busy || (control.hasAttribute('data-originally-disabled'));
		});
	}

	function requireLogin(poll) {
		if (poll && poll.getAttribute('data-logged-in') === '1') {
			return true;
		}
		var loginUrl = poll ? poll.getAttribute('data-login-url') : '';
		if (loginUrl) {
			window.location.assign(loginUrl);
		} else {
			status(poll, 'Sign in to vote.');
		}
		return false;
	}

	function request(poll, method, payload) {
		var url = poll ? poll.getAttribute('data-vote-url') : '';
		if (!url) {
			return Promise.reject(new Error('This poll action is unavailable.'));
		}
		var headers = {
			'Accept': 'application/json',
			'Content-Type': 'application/json'
		};
		var nonce = poll.getAttribute('data-nonce');
		if (nonce) {
			headers['X-WP-Nonce'] = nonce;
		}
		return window.fetch(url, {
			method: method,
			credentials: 'same-origin',
			headers: headers,
			body: method === 'DELETE' ? undefined : JSON.stringify(payload || {})
		}).then(function (response) {
			return response.json().catch(function () {
				return {};
			}).then(function (body) {
				if (!response.ok || !body || body.ok === false) {
					throw new Error(body && body.message ? body.message : 'The poll action could not be completed.');
				}
				return body;
			});
		});
	}

	function replacePoll(poll, html, message) {
		if (!poll || !html) {
			status(poll, message);
			setBusy(poll, false);
			return;
		}
		var template = document.createElement('template');
		template.innerHTML = html.trim();
		var replacement = template.content.querySelector('[data-sabri-poll]');
		if (!replacement) {
			status(poll, message);
			setBusy(poll, false);
			return;
		}
		poll.replaceWith(replacement);
		status(replacement, message);
	}

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('[data-sabri-poll-form]');
		if (!form) {
			return;
		}
		event.preventDefault();
		var poll = closestPoll(form);
		if (!poll || poll.getAttribute('aria-busy') === 'true' || !requireLogin(poll)) {
			return;
		}
		var selected = form.querySelector('input[name="option_key"]:checked');
		if (!selected) {
			status(poll, 'Choose one option before voting.');
			return;
		}
		setBusy(poll, true);
		status(poll, 'Saving vote');
		request(poll, 'POST', { option_key: selected.value })
			.then(function (result) {
				replacePoll(poll, result.data && result.data.html ? result.data.html : '', result.message || 'Vote saved.');
			})
			.catch(function (error) {
				status(poll, error.message || 'The vote could not be saved.');
				setBusy(poll, false);
			});
	});

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-poll-remove]');
		if (!button) {
			return;
		}
		event.preventDefault();
		var poll = closestPoll(button);
		if (!poll || poll.getAttribute('aria-busy') === 'true' || !requireLogin(poll)) {
			return;
		}
		setBusy(poll, true);
		status(poll, 'Removing vote');
		request(poll, 'DELETE', {})
			.then(function (result) {
				replacePoll(poll, result.data && result.data.html ? result.data.html : '', result.message || 'Vote removed.');
			})
			.catch(function (error) {
				status(poll, error.message || 'The vote could not be removed.');
				setBusy(poll, false);
			});
	});
}());
