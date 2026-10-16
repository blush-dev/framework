# Dashboard and counts

What the Home screen and the section panel show. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET dashboard` | The site, `published` (how many entries the site has published), `resume` (the entry the account last saved, while it's a draft or scheduled, or `null`), `yours` and `everyone` (each `{"draft", "scheduled", "published"}`, up to five entries of pages and collections each, as `{"id", "path", "handle", "title", "type", "status", "url", "date", "updated", "authors", "yours"}`; `yours` is `null` for an account without a profile), and `setup` (`{"type", "page", "ownTypes", "media", "others"}`: the pages type, the newest page, whether the site has a type of its own, whether the library has files, and whether another account exists; the last two `false` without the capability to see them) |
| `GET counts` | What the section panel needs counted: `tools` (how many of the Tools screen's buttons the account may use), and, with `site.health`, `health` (how many checks need a look in Site Health's last check) |
