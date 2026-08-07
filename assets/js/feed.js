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

	function actionStatus(bar, message) {
		var status = bar ? bar.querySelector('[data-sabri-action-status]') : null;
		if (status) {
			status.textContent = message || '';
		}
	}

	function setBusy(bar, busy) {
		if (!bar) {
			return;
		}
		bar.setAttribute('aria-busy', busy ? 'true' : 'false');
		Array.prototype.forEach.call(bar.querySelectorAll('[data-sabri-action]'), function (button) {
			button.disabled = !!busy;
		});
	}

	function updateBar(bar, data) {
		if (!bar || !data) {
			return;
		}

		Array.prototype.forEach.call(bar.querySelectorAll('[data-sabri-action="reaction"]'), function (button) {
			var active = button.getAttribute('data-reaction-type') === (data.current_reaction || '');
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
			button.classList.toggle('is-active', active);
		});

		var likeCount = bar.querySelector('[data-count="like"]');
		var dislikeCount = bar.querySelector('[data-count="dislike"]');
		if (likeCount && typeof data.like_count !== 'undefined') {
			likeCount.textContent = String(data.like_count);
		}
		if (dislikeCount && typeof data.dislike_count !== 'undefined') {
			dislikeCount.textContent = String(data.dislike_count);
		}

		var saveButton = bar.querySelector('[data-sabri-action="save"]');
		if (saveButton && typeof data.saved !== 'undefined') {
			var saved = !!data.saved;
			saveButton.setAttribute('aria-pressed', saved ? 'true' : 'false');
			saveButton.classList.toggle('is-active', saved);
			var saveLabel = saveButton.querySelector('[data-save-label]');
			if (saveLabel) {
				saveLabel.textContent = saved ? 'Saved' : 'Save';
			}
		}

		var followButton = bar.querySelector('[data-sabri-action="follow"]');
		if (followButton && typeof data.following !== 'undefined') {
			var following = !!data.following;
			followButton.setAttribute('aria-pressed', following ? 'true' : 'false');
			followButton.classList.toggle('is-active', following);
			var followLabel = followButton.querySelector('[data-follow-label]');
			if (followLabel) {
				followLabel.textContent = following ? 'Following' : 'Follow';
			}
			var followerCount = followButton.querySelector('[data-count="followers"]');
			if (followerCount && typeof data.follower_count !== 'undefined') {
				followerCount.textContent = String(data.follower_count);
			}
		}
	}

	function request(context, url, method, payload) {
		var headers = {
			'Accept': 'application/json',
			'Content-Type': 'application/json'
		};
		var nonce = context ? context.getAttribute('data-nonce') : '';
		if (nonce) {
			headers['X-WP-Nonce'] = nonce;
		}

		return window.fetch(url, {
			method: method,
			credentials: 'same-origin',
			headers: headers,
			body: method === 'GET' || method === 'DELETE' ? undefined : JSON.stringify(payload || {})
		}).then(function (response) {
			return response.json().catch(function () {
				return {};
			}).then(function (body) {
				if (!response.ok || !body || body.ok === false) {
					var error = new Error(body && body.message ? body.message : 'The action could not be completed.');
					error.status = response.status;
					throw error;
				}
				return body;
			});
		});
	}

	function handleInteraction(button) {
		var bar = button.closest('[data-sabri-interactions]');
		if (!bar || bar.getAttribute('aria-busy') === 'true') {
			return;
		}

		if (bar.getAttribute('data-logged-in') !== '1') {
			var loginUrl = bar.getAttribute('data-login-url');
			if (loginUrl) {
				window.location.assign(loginUrl);
			} else {
				actionStatus(bar, 'Sign in to use this action.');
			}
			return;
		}

		var action = button.getAttribute('data-sabri-action');
		var method = 'POST';
		var url = '';
		var payload = {};

		if (action === 'reaction') {
			url = bar.getAttribute('data-reaction-url');
			payload.reaction_type = button.getAttribute('data-reaction-type');
			if (button.getAttribute('aria-pressed') === 'true') {
				method = 'DELETE';
			}
		} else if (action === 'save') {
			url = bar.getAttribute('data-save-url');
			if (button.getAttribute('aria-pressed') === 'true') {
				method = 'DELETE';
			}
		} else if (action === 'follow') {
			url = bar.getAttribute('data-follow-url');
			if (button.getAttribute('aria-pressed') === 'true') {
				method = 'DELETE';
			}
		}

		if (!url) {
			actionStatus(bar, 'This action is unavailable.');
			return;
		}

		setBusy(bar, true);
		actionStatus(bar, 'Saving');
		request(bar, url, method, payload)
			.then(function (result) {
				updateBar(bar, result.data || {});
				actionStatus(bar, result.message || 'Saved.');
			})
			.catch(function (error) {
				actionStatus(bar, error.message || 'The action could not be completed.');
			})
			.then(function () {
				setBusy(bar, false);
			});
	}

	function preferenceStatus(agency, message) {
		var target = agency ? agency.querySelector('[data-sabri-feed-preference-status]') : null;
		if (target) {
			target.textContent = message || '';
		}
	}

	function setPreferenceBusy(agency, busy) {
		if (!agency) {
			return;
		}
		agency.setAttribute('aria-busy', busy ? 'true' : 'false');
		Array.prototype.forEach.call(agency.closest('.sabri-hnf-feed').querySelectorAll('[data-sabri-feed-preference]'), function (button) {
			button.disabled = !!busy;
		});
	}

	function handleFeedPreference(button) {
		var feed = button.closest('.sabri-hnf-feed');
		var agency = feed ? feed.querySelector('[data-sabri-feed-agency]') : null;
		if (!agency || agency.getAttribute('aria-busy') === 'true') {
			return;
		}
		if (agency.getAttribute('data-logged-in') !== '1') {
			var loginUrl = agency.getAttribute('data-login-url');
			if (loginUrl) {
				window.location.assign(loginUrl);
			} else {
				preferenceStatus(agency, 'Sign in to change Feed preferences.');
			}
			return;
		}
		var url = agency.getAttribute('data-preferences-url');
		if (!url) {
			preferenceStatus(agency, 'Feed preferences are unavailable.');
			return;
		}
		var action = button.getAttribute('data-sabri-feed-preference') || '';
		var value = button.getAttribute('data-value') || '';
		var duration = parseInt(button.getAttribute('data-duration') || '0', 10) || 0;
		setPreferenceBusy(agency, true);
		preferenceStatus(agency, 'Saving Feed preference');
		request(agency, url, 'POST', { action: action, value: value, duration: duration })
			.then(function (result) {
				preferenceStatus(agency, result.message || 'Feed preference updated.');
				if (action === 'hide-post') {
					var card = button.closest('.sabri-hnf-card');
					if (card) {
						card.remove();
					}
					setPreferenceBusy(agency, false);
					return;
				}
				window.setTimeout(function () { window.location.reload(); }, 250);
			})
			.catch(function (error) {
				preferenceStatus(agency, error.message || 'The Feed preference could not be saved.');
				setPreferenceBusy(agency, false);
			});
	}

	function reportContext(form) {
		return form ? form.closest('[data-sabri-interactions], [data-sabri-comments]') : null;
	}

	function reportStatus(form, message) {
		var target = form ? form.querySelector('[data-report-status]') : null;
		if (target) {
			target.textContent = message || '';
		}
	}

	function setReportBusy(form, busy) {
		if (!form) {
			return;
		}
		form.setAttribute('aria-busy', busy ? 'true' : 'false');
		Array.prototype.forEach.call(form.querySelectorAll('button, select, textarea'), function (control) {
			control.disabled = !!busy;
		});
	}

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('[data-sabri-report-form]');
		if (!form) {
			return;
		}
		event.preventDefault();
		var context = reportContext(form);
		if (!context || form.getAttribute('aria-busy') === 'true') {
			return;
		}
		if (context.getAttribute('data-logged-in') !== '1') {
			var loginUrl = context.getAttribute('data-login-url');
			if (loginUrl) {
				window.location.assign(loginUrl);
			}
			return;
		}

		var reasonField = form.querySelector('[data-report-reason]');
		var noteField = form.querySelector('[data-report-note]');
		var reason = reasonField ? reasonField.value : '';
		var note = noteField ? noteField.value.trim() : '';
		var url = form.getAttribute('data-report-url');
		if (!reason || !url) {
			reportStatus(form, !reason ? 'Select a report reason.' : 'Reporting is unavailable.');
			return;
		}
		if (reason === 'other' && note.length < 10) {
			reportStatus(form, 'Describe the concern when selecting Other.');
			return;
		}

		setReportBusy(form, true);
		reportStatus(form, 'Submitting report');
		request(context, url, 'POST', {
			object_type: form.getAttribute('data-object-type'),
			object_id: parseInt(form.getAttribute('data-object-id'), 10) || 0,
			reason: reason,
			note: note
		})
			.then(function (result) {
				form.reset();
				reportStatus(form, result.message || 'Report submitted for confidential review.');
				var details = form.closest('[data-sabri-report-control]');
				if (details) {
					window.setTimeout(function () { details.open = false; }, 900);
				}
			})
			.catch(function (error) {
				reportStatus(form, error.message || 'The report could not be submitted.');
			})
			.then(function () {
				setReportBusy(form, false);
			});
	});

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

		document.addEventListener('click', function (event) {
			var preferenceButton = event.target.closest('[data-sabri-feed-preference]');
			if (preferenceButton) {
				event.preventDefault();
				handleFeedPreference(preferenceButton);
				return;
			}
			var button = event.target.closest('[data-sabri-action]');
			if (!button) {
				return;
			}
			event.preventDefault();
			handleInteraction(button);
		});
	});
}());
