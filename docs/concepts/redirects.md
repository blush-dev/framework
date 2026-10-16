# Redirects

Redirects send visitors from an old address to a new one. The easiest
place to manage them is the admin's Redirects screen, under Tools (see
[the admin](../admin/README.md)). When you rename or move a published
entry in the admin, **Redirect the old address here** adds one for you.

## The redirects file

Blush keeps them in `user/data/redirects.json`, a list with one
redirect each: the old path in `from`, and where it leads, either a path
or a whole address in `to`, or an entry by its id in `entry`. A redirect
to an entry follows it: if the entry moves again, the redirect leads to
its new address, and while the entry isn't live (a draft, or in the
trash), the old address is a 404. A redirect is permanent (301) unless
it has a `status`, such as 302 for a temporary one. Placeholders such as
`{name}` carry parts of the path across:

```json
[
    {"from": "/old-about", "entry": "0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74"},
    {"from": "/news/{name}", "to": "/blog/{name}"},
    {"from": "/sale", "to": "https://shop.example.com/autumn", "status": 302}
]
```

Redirects the admin adds also say when they were added (`added`), by
whom (`by`, an account's id), and how (`via`: `rename`, `move`, or
`import`); you can leave those out of your own.

## How they match

Each old path can have one redirect. Old paths match as written, so
`/About` and `/about` are two addresses, but a trailing slash doesn't
count. Blush adds an `id` to each when it saves the file. When the admin
adds a redirect for an entry that moved, redirects that led to its old
address lead to the entry too, so visitors never pass through two
redirects, and renaming it back removes the one it doesn't need. On a
live site, publish after editing the file yourself (`bin/blush
publish`); redirects the admin adds or changes work right away. A site
[kept in a database](../running/sqlite.md) keeps its redirects there
instead.

Redirects apply only when nothing else answers the URL, so they never hide
a real page. Redirects in `config/routes.php` come before these (see
[Configuration](../reference/configuration/README.md)).
