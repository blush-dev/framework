# Site Health

**Home → Site Health** answers one question: is anything wrong?

It's for accounts with `site.health`: owners, unless an owner gives it to
another role. It shows the last check, with when it ran, and checks
again when you choose **Run a Check**; the first time you open it, it
checks right away. The number beside **Site Health** in the panel is
how many checks needed a look last time. Checking content and media
files again on their screens, as after a fix, updates their part of it.
Blush keeps the last check in `storage/health.json`.

| Page | What it covers |
|---|---|
| [Fixing problems](fixing-problems.md) | A check's screen: fixing rows and groups, ignoring, and finding rows |
| [Content and media checks](checks.md) | What each content and media check finds |

## Checks, issues, and areas

**Checks** shows how many checks need attention, how many pass, and how
many ran. **Issues** lists what isn't passing, failures first, each with
what it found and what to do. A row with a chevron opens where it's
fixed. **Areas** lists the five areas the checks are in, and whether
each is clear:

| Area | What it checks |
|---|---|
| Content | Entries' files: problems in their front matter, ids, terms and profiles with no file, collection folders, and file names; and types still written as taxonomies |
| Media | Library files' ids, their details files, and image sizes |
| Extensions | A theme, plugins, or icon packs that are on but can't run |
| System | PHP and its extensions, `.env`, debugging and `APP_URL` in production, whether the site's address serves private files (in production), the public folder, writable storage, background jobs, and secrets |
| Accounts | Whether the site has an owner |

Extensions, System, and Accounts are the checks
[`bin/blush doctor`](../../reference/cli/README.md) runs. Here they run under the web server's
PHP, which can differ from the command line's.

## Requirements

**Requirements** compares what Blush needs with what this server has:
the PHP version, the required extensions and what uses them (`dom`,
`intl`, `mbstring`), the optional ones and what uses them (`opcache` and
`fileinfo` are recommended; `zip` installs extensions from a `.zip`,
`exif` reads photos' embedded details, `zlib` reads compressed PDFs'
details, and `apcu` keeps the cache in memory, needed only when the
cache or one of its stores uses the `apcu` driver), PHP's upload
limits against the largest upload the [Media settings](../settings/media.md) allow,
and the storage folders Blush writes to. Under **Plugins and Themes**
are the PHP extensions the plugins, themes, and icon packs that are on
require or suggest, each naming which ones ask, and a PHP version when
one needs a newer PHP than Blush does. One that requires an extension
that's missing doesn't run until it's installed.

## Site and server

**Site & Server** lists the facts you'd be asked for in a bug
report, about this site and the server it runs on, in plain words: the
theme by its name, languages by theirs, PHP's memory and time limits in
megabytes and seconds, with the PHP setting each comes from beneath its
label. **Copy Report**, on either tab, copies the requirements and the
facts as text, the facts as they're configured (`memory_limit: 256M`,
`languages: en, es`), which is what a bug report needs.
