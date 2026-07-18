(function () {
	'use strict';

	function closestThread(node) {
		return node && node.closest ? node.closest('[data-sabri-comments]') : null;
	}

	function status(thread, message) {
		var target = thread ? thread.querySelector('[data-comment-status]') : null;
		if (target) {
			target.textContent = message || '';
		}
	}

	function setBusy(thread, busy) {
		if (!thread) {
			return;
		}
		thread.setAttribute('aria-busy', busy ? 'true' : 'false');
		Array.prototype.forEach.call(thread.querySelectorAll('button, textarea'), function (control) {
			control.disabled = !!busy;
		});
	}

	function request(thread, url, method, payload) {
		var headers = {
			'Accept': 'application/json',
			'Content-Type': 'application/json'
		};
		var nonce = thread.getAttribute('data-nonce');
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
					var error = new Error(body && body.message ? body.message : 'The comment action could not be completed.');
					error.status = response.status;
					throw error;
				}
				return body;
			});
		});
	}

	function replaceThread(thread, html, message) {
		if (!thread || !html) {
			status(thread, message);
			return thread;
		}
		var template = document.createElement('template');
		template.innerHTML = html.trim();
		var replacement = template.content.querySelector('[data-sabri-comments]');
		if (!replacement) {
			status(thread, message);
			return thread;
		}
		thread.replaceWith(replacement);
		status(replacement, message);
		return replacement;
	}

	function resetForm(thread) {
		var form = thread ? thread.querySelector('[data-sabri-comment-form]') : null;
		if (!form) {
			return;
		}
		form.reset();
		var parent = form.querySelector('[data-comment-parent]');
		var commentId = form.querySelector('[data-comment-id]');
		var context = form.querySelector('[data-comment-form-context]');
		var submit = form.querySelector('[type="submit"]');
		var cancel = form.querySelector('[data-comment-cancel]');
		if (parent) {
			parent.value = '0';
		}
		if (commentId) {
			commentId.value = '0';
		}
		if (context) {
			context.textContent = 'Write a comment';
		}
		if (submit) {
			submit.textContent = 'Post Comment';
		}
		if (cancel) {
			cancel.hidden = true;
		}
		status(thread, '');
	}

	function requireLogin(thread) {
		if (thread && thread.getAttribute('data-logged-in') === '1') {
			return true;
		}
		var loginUrl = thread ? thread.getAttribute('data-login-url') : '';
		if (loginUrl) {
			window.location.assign(loginUrl);
		}
		return false;
	}

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('[data-sabri-comment-form]');
		if (!form) {
			return;
		}
		event.preventDefault();
		var thread = closestThread(form);
		if (!thread || thread.getAttribute('aria-busy') === 'true' || !requireLogin(thread)) {
			return;
		}

		var contentField = form.querySelector('[data-comment-content]');
		var parentField = form.querySelector('[data-comment-parent]');
		var commentField = form.querySelector('[data-comment-id]');
		var content = contentField ? contentField.value.trim() : '';
		var parentId = parentField ? parseInt(parentField.value, 10) || 0 : 0;
		var commentId = commentField ? parseInt(commentField.value, 10) || 0 : 0;
		var method = commentId > 0 ? 'PATCH' : 'POST';
		var url = commentId > 0 ? thread.getAttribute('data-comment-base') + commentId : thread.getAttribute('data-create-url');

		if (content.length < 2 || !url) {
			status(thread, content.length < 2 ? 'Please enter a meaningful comment.' : 'This comment action is unavailable.');
			return;
		}

		setBusy(thread, true);
		status(thread, 'Saving comment');
		request(thread, url, method, { content: content, parent_id: parentId })
			.then(function (result) {
				var next = replaceThread(thread, result.data && result.data.html ? result.data.html : '', result.message || 'Comment saved.');
				resetForm(next);
			})
			.catch(function (error) {
				status(thread, error.message || 'The comment could not be saved.');
				setBusy(thread, false);
			});
	});

	document.addEventListener('click', function (event) {
		var reply = event.target.closest('[data-comment-reply]');
		var edit = event.target.closest('[data-comment-edit]');
		var remove = event.target.closest('[data-comment-delete]');
		var cancel = event.target.closest('[data-comment-cancel]');
		var trigger = reply || edit || remove || cancel;
		if (!trigger) {
			return;
		}

		var thread = closestThread(trigger);
		if (!thread || thread.getAttribute('aria-busy') === 'true') {
			return;
		}
		event.preventDefault();

		if (cancel) {
			resetForm(thread);
			return;
		}
		if (!requireLogin(thread)) {
			return;
		}

		var form = thread.querySelector('[data-sabri-comment-form]');
		if (!form) {
			return;
		}
		var parentField = form.querySelector('[data-comment-parent]');
		var commentField = form.querySelector('[data-comment-id]');
		var contentField = form.querySelector('[data-comment-content]');
		var context = form.querySelector('[data-comment-form-context]');
		var submit = form.querySelector('[type="submit"]');
		var cancelButton = form.querySelector('[data-comment-cancel]');
		var commentId = parseInt(trigger.getAttribute('data-comment-id'), 10) || 0;

		if (reply) {
			if (parentField) {
				parentField.value = String(commentId);
			}
			if (commentField) {
				commentField.value = '0';
			}
			if (contentField) {
				contentField.value = '';
				contentField.focus();
			}
			if (context) {
				context.textContent = 'Replying to ' + (reply.getAttribute('data-author-name') || 'comment');
			}
			if (submit) {
				submit.textContent = 'Post Reply';
			}
			if (cancelButton) {
				cancelButton.hidden = false;
			}
			return;
		}

		if (edit) {
			var item = edit.closest('[data-comment-item]');
			if (commentField) {
				commentField.value = String(commentId);
			}
			if (parentField) {
				parentField.value = '0';
			}
			if (contentField) {
				contentField.value = item ? (item.getAttribute('data-comment-content') || '') : '';
				contentField.focus();
			}
			if (context) {
				context.textContent = 'Editing comment';
			}
			if (submit) {
				submit.textContent = 'Update Comment';
			}
			if (cancelButton) {
				cancelButton.hidden = false;
			}
			return;
		}

		if (remove) {
			if (!window.confirm('Remove this comment? Replies will remain in the thread.')) {
				return;
			}
			var deleteUrl = thread.getAttribute('data-comment-base') + commentId;
			setBusy(thread, true);
			status(thread, 'Removing comment');
			request(thread, deleteUrl, 'DELETE', {})
				.then(function (result) {
					replaceThread(thread, result.data && result.data.html ? result.data.html : '', result.message || 'Comment removed.');
				})
				.catch(function (error) {
					status(thread, error.message || 'The comment could not be removed.');
					setBusy(thread, false);
				});
		}
	});
}());
