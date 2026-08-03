# File 21 Public Markdown and Shell Integration Correction R3

## Confirmed defect

A legacy/general singular WordPress article containing raw Markdown was not File 21-owned and therefore bypassed File 21 public formatting and the File 20 managed-layout recovery hook. The public title exposed a leading `#`; body emphasis markers remained literal; and the fixed desktop sidebar could cover the theme content column.

## Correction

- Ownership remains strict: legacy articles are not reclassified as File 21-owned.
- Explicit raw Markdown artifacts opt only the queried public singular article into display-integrity repair.
- The queried title is normalized to plain title text.
- Body headings, bold and single-asterisk emphasis are rendered conservatively; code/pre/script/style blocks are excluded.
- A neutral `sabri-hnf-content-integrity-single` body class and stable content wrapper provide the cross-file layout signal.
- Ordinary WordPress content without supported Markdown artifacts remains untouched.

## Review rounds

1. Full PHP syntax, targeted public-content regression, PHPUnit, PHPStan and WordPress Coding Standards.
2. Fresh adversarial regression for ordinary-content isolation, title scoping, code/pre preservation, ownership boundaries, asset dependency removal and cache invalidation.

## Lifecycle boundary

Repository coding and automated QA do not equal Hostinger staging or live acceptance.
