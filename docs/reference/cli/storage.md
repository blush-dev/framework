# Storage

A site keeps its data as files by default. These commands move it into a
SQLite database (`storage/database/site.sqlite`); see [SQLite](../../running/sqlite.md).

| Command | What it does |
|---|---|
| `storage:copy` | Copy the site's content (with the links between entries), data, accounts, roles, secrets (still encrypted), and jobs from files into SQLite, ids kept, each entry's front matter as written. Sessions aren't copied, so everyone signs in again. Content files without an id aren't copied (run `content:ids --write` first). It refuses a database that already holds records; `--replace` replaces them. `--from=` and `--to=` name the drivers (`filesystem` and `sqlite`, the only way for now). The site keeps using files until `STORAGE_DRIVER=sqlite` is set. A large site may need more memory: `php -d memory_limit=1G bin/blush storage:copy`. |
| `storage:sync` | Make or update the tables the database keeps, with their columns and indexes, and refresh what queries plan by. Tables are made when first used, so this is for deploys. On files it does nothing. |
