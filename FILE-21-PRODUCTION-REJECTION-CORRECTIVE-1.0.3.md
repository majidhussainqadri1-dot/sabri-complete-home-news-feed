# File 21 Production-Rejection Corrective Release 1.0.3

## Scope

This release addresses the confirmed live/runtime defects discovered after File 21 1.0.2 was installed on Hostinger. It changes only File 21 and does not modify File 20, WordPress content, or the live site.

## Corrected boundaries

- Safe Boot settings normalization is re-entry safe and uses a non-filterable local key sanitizer.
- Public Home/Profile read surfaces are observable without database writes.
- Metadata normalization runs only through an explicit administrator action with capability, nonce, batching, audit, and source preservation.
- File 21 registers both `sabri_shell_home_main` and `sabri_shell_news_main`.
- `/news/` is canonical after explicit Editorial News activation; `/sabri-news/` and `/blog/` redirect only when that gate is enabled.
- Legacy File 04 Home/News shortcodes are replaced at render time only; saved Page content is unchanged.
- Exactly ten Home rows render; unavailable providers produce an honest empty state.
- Breaking News is limited to the public main Home/News loop and renders once.
- Core-post SQL eligibility is applied before pagination, followed by object-level authorization.
- File 20 integration is “Connected” only when all five required native slots are explicitly advertised.
- Safe Boot exposes authenticated `/status` and `/schema` diagnostics without loading the full runtime.
- Duplicate folder variants resolve to the highest-version canonical plugin and trigger one controlled administrator reload.

## Fail-closed controls

- Editorial News gates remain disabled by default.
- Automatic publication remains disabled except the documented privileged-author policy.
- Automatic File 04 migration remains disabled.
- No public GET request performs migration or post-meta writes.
- No legacy source is deleted.
- Runtime version is `1.0.3`; schema remains `1.0.0`.

## External acceptance still required

- File 20 native-slot release and combined staging test.
- Hostinger staging fresh install, upgrade, rollback, Safe Boot retry, duplicate-folder test, rewrite flush, cache purge, and screenshots.
- Real Founder/Administrator/Doctor permissions and publishing matrix.
- Live deployment and Founder visual acceptance.
