import { chromium, firefox, webkit } from 'playwright';
const engines = { chromium, firefox, webkit };
const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>${await (await import('node:fs/promises')).readFile('assets/css/phase5-public.css','utf8')}</style></head><body><section class="sabri-breaking-strip" aria-labelledby="breaking"><div class="sabri-breaking-strip__inner"><h2 id="breaking">Breaking News</h2><ul><li><a href="/news/story/">Verified story</a><time datetime="2026-07-25T00:00:00Z">Temporary alert</time></li></ul></div></section><main><section class="sabri-submission-portal" aria-labelledby="submit-title"><h1 id="submit-title">Submit Editorial News</h1><form><label for="headline">Headline</label><input id="headline" required><label for="body">Article</label><textarea id="body" required></textarea><fieldset><legend>Declarations</legend><label><input type="checkbox" required> No patient identifiers</label></fieldset><button>Save</button></form><div aria-live="polite"></div></section><section class="sabri-news-sources" aria-labelledby="sources"><h2 id="sources">Sources and evidence</h2><ol><li><a href="https://example.test/source">Official source</a></li></ol></section></main></body></html>`;
const requested = process.env.SABRI_PHASE5_BROWSER || '';
const selected = requested ? { [requested]: engines[requested] } : engines;
if (requested && !engines[requested]) throw new Error(`Unknown browser: ${requested}`);
for (const [name, launcher] of Object.entries(selected)) {
	const browser = await launcher.launch({ headless: true });
	const page = await browser.newPage({ javaScriptEnabled: true, viewport: { width: 390, height: 844 } });
	await page.setContent(html);
	if (await page.locator('h1').count() !== 1) throw new Error(`${name}: missing single h1`);
	if (await page.locator('label[for="headline"]').count() !== 1) throw new Error(`${name}: missing form label`);
	if (await page.locator('[aria-live="polite"]').count() !== 1) throw new Error(`${name}: missing live region`);
	await page.keyboard.press('Tab');
	if (!(await page.evaluate(() => document.activeElement && document.activeElement.tagName === 'A'))) throw new Error(`${name}: keyboard focus did not reach breaking link`);
	const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
	if (overflow) throw new Error(`${name}: mobile horizontal overflow`);
	await browser.close();
	const noJs = await launcher.launch({ headless: true });
	const noJsPage = await noJs.newPage({ javaScriptEnabled: false });
	await noJsPage.setContent(html);
	if (await noJsPage.locator('form').count() !== 1 || await noJsPage.locator('.sabri-breaking-strip a').count() !== 1) throw new Error(`${name}: no-JavaScript fallback missing`);
	await noJs.close();
}
console.log('Phase 5 Chromium, Firefox, WebKit, mobile, keyboard, and no-JavaScript browser tests passed.');
