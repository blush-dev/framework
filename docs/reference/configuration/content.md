# Content

The homepage, built-in types, and the types and relations the admin
keeps in `user/data/`.

`config/content.php` · `Blush\Content\ContentConfig`

| Option | Default | What it does |
|---|---|---|
| `home` | `null` | A type whose listing is the homepage |
| `disabled` | `[]` | Built-in types to turn off (`'profile'`) |
| `dataTypes` | `true` | Whether types in `user/data/types/` and relations in `user/data/relations/` are read |
| `dataTypeUrls` | `true` | Whether those types may set their own `urls` |
| `autoIndex` | `true` | Whether development requests pick up content changes |

See [Content types](../../concepts/content-types.md).
