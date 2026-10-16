# Feeds

Which feed formats the site offers, and what's in them.

`config/feed.php` · `Blush\Feed\FeedConfig`

| Option | Default | What it does |
|---|---|---|
| `formats` | RSS, Atom, and JSON | Which formats to offer (`FeedFormat::Rss`, `::Atom`, `::Json`) |
| `content` | `true` | Include each entry's full content; off, only its summary |
| `limit` | `10` | Entries per feed |

Feeds are turned on per [content type](../../concepts/content-types.md) with its `feed`
option (see [Archives and feeds](../../concepts/archives-and-feeds.md)).
The admin saves `content` as `fullContent`.
