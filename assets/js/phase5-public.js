(() => {
	'use strict';
	const strip = document.querySelector('[data-sabri-breaking-strip]');
	if (strip) strip.setAttribute('data-enhanced', 'true');
	const form = document.querySelector('[data-sabri-submission-form]');
	if (!form || !window.fetch) return;
	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const status = form.parentElement.querySelector('.sabri-submission-status');
		const data = new FormData(form);
		const payload = {
			title: String(data.get('title') || ''), summary: String(data.get('summary') || ''), body: String(data.get('body') || ''),
			source_urls: String(data.get('source_urls') || '').split(/\r?\n/).map((value) => value.trim()).filter(Boolean),
			declarations: {
				owns_text: data.has('owns_text'), patient_identifiers_absent: data.has('patient_identifiers_absent'),
				conflicts_declared: data.has('conflicts_declared'), sponsorship_declared: data.has('sponsorship_declared'), ai_assistance_declared: data.has('ai_assistance_declared')
			}
		};
		status.textContent = 'Saving…';
		try {
			const response = await fetch('/wp-json/sabri-home-news-feed/v1/submissions', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':window.wpApiSettings ? window.wpApiSettings.nonce : ''},body:JSON.stringify(payload)});
			const result = await response.json();
			status.textContent = response.ok && result.success ? 'Submission draft saved.' : 'The submission could not be saved.';
		} catch (error) { status.textContent = 'The submission could not be saved.'; }
	});
})();
