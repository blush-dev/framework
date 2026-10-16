# Redirects

The Redirects screen's redirects. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

With `site.redirects` (a 403 otherwise), a redirect is named by its old
path, `from`:

- `GET redirects` lists them, sorted by `from`, a page at a time:
  `tab` (`all`, `permanent`, `temporary`, or `problems`), `search`,
  `goes` (`here` or `away`), `page`, and `per` (20 by default; 10, 50,
  or 100). The answer has `redirects`, `total`, `page`, `pages`, `per`,
  `counts` (for each tab), `code` (the redirects from code, which come
  first, as `{from, to, status}`), and `trace`: when the search is an
  address, its `path` and `hops`, each step a visitor would take
  (`page`, `redirect`, `away`, `gone`, `missing`, or `loop`), or
  `other`, the host of an address on another site. Each redirect has
  `from`, `to` (or `null`), `entry` (`{id, title, url, type, state}`,
  `state` being `live`, `draft`, `scheduled`, `trash`, `hidden`, or
  `deleted`), `status`, `added`, `by` (the account that added it: `{name, username, you}`, `name` and `username` `null` for a removed account), `via`,
  `problem` (`{kind, label, message, final}`, or `null`), and `stored`,
  the row as the file keeps it.
- `POST redirects/check` takes `{from, to, entry, status, was}` and
  answers what the form says about each field (`from`, `to`, and
  `status`, lists of `{kind, parts, fix}`, `kind` being `bad`, `warn`,
  or `say`) and the `row` it would save, or `null` when something stops
  it.
- `POST redirects` adds one, or changes the one from `was`, with the
  same values; a 422 has the messages. A path that's a live entry's
  address is saved as the entry. The answer has the `redirect` and, for
  a change, `was`, the row as it was kept.
- `POST redirects/delete` takes `{from: [...]}` and answers `deleted`,
  the rows as they were kept.
- `POST redirects/status` takes `{from: [...], status}` and answers
  `changed`, the rows that changed, as they were kept.
- `POST redirects/restore` takes `{rows: [...], remove: [...]}`: it
  removes the redirects from `remove`, then writes `rows` as given.
  The admin's Undo uses it.
