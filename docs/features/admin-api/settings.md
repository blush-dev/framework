# Settings

The Settings screens' site-wide settings. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET settings` | The site-wide settings, to show: `{"groups": [{"key", "title", "hint", "file", "note", "items": [{"key", "label", "value", "kind", "default", "help", "warning"}]}]}`. `kind` is `text`, `mono`, `bool` (the value is `true` or `false`), or `list`; `default` is whether it's unchanged (`null` for one that follows from others); `note` marks code with backticks. A setting the admin changes adds its `setting` (`feed.limit`), its `field` (as forms take it, with `choices` and a `caption`), the `input` the form starts from, and whether it's `saved`. A shown one may add a `link` (`{"label", "href"}`, a page on the site) or `links` (`{"label", "to"}`, admin paths). The screens are `general`, `reading`, `search`, `ai`, and `system`. Secrets are never sent. Needs `site.settings` |
| `GET settings/date-format` | How a date or time format reads now: `format`, `kind` (`date` or `time`), and the `locale` to read it in (the site's by default). Answers `{"text"}`, or a `422` saying what's wrong with the format. Needs `site.settings` |
