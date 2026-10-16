# SQLite

A site keeps everything in files by default, which suits most sites. A
site with many thousands of entries outgrows that: Blush keeps an index
of every file, and at about 10,000 entries reading it needs more memory
than PHP allows by default (128 MB). Such a site keeps its data in a
SQLite database instead, one file, `storage/database/site.sqlite`. PHP
needs its `pdo_sqlite` extension (`bin/blush doctor` says whether it has
it).

## Moving a site to SQLite

```sh
bin/blush content:ids --write                     # every entry needs an id
php -d memory_limit=1G bin/blush storage:copy     # files into SQLite
```

Then set `STORAGE_DRIVER=sqlite` in `.env` (or `driver` in
`config/storage.php`; see [Storage configuration](../reference/configuration/storage.md)).
Everyone signs in again. Your files stay where they were; the site no
longer reads them, so keep them as a backup or remove them.

## Running on SQLite

- **Edit in the admin** (or create with `content:new`). There are no
  content files to edit, and `content:index` has nothing to do.
- **Back up `storage/database/site.sqlite`**, with `user/` and your
  media. It's kept in `storage/`, out of git, since it holds accounts and
  sessions beside content: publishing with git carries code and media,
  not content. `cache:clear` never touches it. [Backups](backups.md)
  made by Blush hold a copy of it too.
- **Run `bin/blush storage:sync` after each deploy**, beside
  `cache:compile`.
- Tools for files (`content:ids`, `content:filenames`, `content:folders`,
  `content:refs`, `content:terms`, `content:parents`) aren't offered,
  and Site Health checks only what a database can get wrong.
- A tag, category, or author has to be an entry before an entry can name
  it; create it first.
