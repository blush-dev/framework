# Tools and logs

The Tools screen's buttons, and the site's log. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET tools` | The Tools screen's buttons the account may use, as `{"groups": [{"source": {"kind", "label"}, "tools": [{"key", "title", "description", "confirm"}]}]}`; a source's `kind` is `core`, `site`, `plugin`, or `other` |
| `POST tools/{vendor}/{name}` | Run a tool by its key; the answer is `{"successful", "message", "details"}`, or `{"job"}` for one that runs in the background |
| `GET logs` | The end of the site's log, with `site.logs`: `{"driver", "file", "size", "entries"}`, its last 50 entries, newest first, each `{"time", "channel", "level", "message", "details"}` (`details` is the lines under it, such as an exception's trace); `file` is `null` unless the driver writes one |
| `GET logs/download` | The whole log file, to save, with `site.logs` |
