(function () {
	'use strict';

	var config = window.SabriHnfNextGeneration || {};
	var textActions = ['quote', 'qna-question', 'qna-answer', 'expert-context'];

	function setStatus(message, isError) {
		document.querySelectorAll('[data-sabri-ng-status]').forEach(function (node) {
			node.textContent = message || '';
			node.setAttribute('data-error', isError ? '1' : '0');
		});
	}

	function actionLabel(action) {
		return {
			quote: 'Add quote or context',
			'qna-question': 'Ask a question',
			'qna-answer': 'Write an answer',
			'expert-context': 'Add expert context'
		}[action] || 'Enter text';
	}

	function postAction(action, payload) {
		if (!config.endpoint || !config.loggedIn) {
			return Promise.reject(new Error((config.i18n && config.i18n.login) || 'Please sign in.'));
		}
		return window.fetch(config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || ''
			},
			body: JSON.stringify(Object.assign({}, payload, { action: action }))
		}).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok || !json || json.ok === false) {
					throw new Error((json && json.message) || ((config.i18n && config.i18n.error) || 'Action failed.'));
				}
				return json.data || {};
			});
		});
	}

	function openEditor(trigger, action) {
		var postId = parseInt(trigger.getAttribute('data-post-id') || '0', 10) || 0;
		var questionId = trigger.getAttribute('data-question-id') || '';
		var dialog = document.createElement('dialog');
		var titleId = 'sabri-ng-editor-title-' + String(Date.now());
		dialog.className = 'sabri-hnf-ng-editor-dialog';
		dialog.setAttribute('aria-labelledby', titleId);

		var form = document.createElement('form');
		form.method = 'dialog';
		var title = document.createElement('h2');
		title.id = titleId;
		title.textContent = actionLabel(action);
		var label = document.createElement('label');
		label.textContent = actionLabel(action);
		var textarea = document.createElement('textarea');
		textarea.rows = 6;
		textarea.required = true;
		textarea.maxLength = 5000;
		textarea.setAttribute('aria-describedby', titleId);
		label.appendChild(textarea);

		var actions = document.createElement('div');
		actions.className = 'sabri-hnf-ng-dialog-actions';
		var submit = document.createElement('button');
		submit.type = 'submit';
		submit.className = 'sabri-hnf-ng-button';
		submit.textContent = 'Submit';
		var cancel = document.createElement('button');
		cancel.type = 'button';
		cancel.className = 'sabri-hnf-ng-button';
		cancel.textContent = 'Cancel';
		actions.appendChild(submit);
		actions.appendChild(cancel);
		form.appendChild(title);
		form.appendChild(label);
		form.appendChild(actions);
		dialog.appendChild(form);
		document.body.appendChild(dialog);

		function close() {
			if (typeof dialog.close === 'function' && dialog.open) { dialog.close(); }
			dialog.remove();
			trigger.focus();
		}

		cancel.addEventListener('click', close);
		dialog.addEventListener('cancel', function (event) {
			event.preventDefault();
			close();
		});
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var text = textarea.value.trim();
			if (!text) {
				textarea.focus();
				return;
			}
			submit.disabled = true;
			var payload = { post_id: postId, text: text };
			if ('qna-answer' === action) { payload.question_id = questionId; }
			postAction(action, payload).then(function () {
				setStatus((config.i18n && config.i18n.saved) || 'Saved.', false);
				close();
			}).catch(function (error) {
				setStatus(error.message || 'Action failed.', true);
				submit.disabled = false;
				textarea.focus();
			});
		});

		if (typeof dialog.showModal === 'function') {
			dialog.showModal();
		} else {
			dialog.setAttribute('open', 'open');
			dialog.setAttribute('role', 'dialog');
			dialog.setAttribute('aria-modal', 'true');
		}
		textarea.focus();
	}

	/* Capture before the older bubble handler so text actions never need window.prompt. */
	document.addEventListener('click', function (event) {
		var trigger = event.target.closest('[data-sabri-ng-action]');
		if (!trigger) { return; }
		var action = trigger.getAttribute('data-sabri-ng-action') || '';
		if (textActions.indexOf(action) < 0) { return; }
		event.preventDefault();
		event.stopImmediatePropagation();
		openEditor(trigger, action);
	}, true);
}());
