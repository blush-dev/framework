# Which template is used

Blush picks the most specific template your theme (or its parents) has:

| Page | Templates tried, in order |
|---|---|
| An entry | `single-{type}-{slug}`, `single-{type}`, (`single-terms`), `single-{kind}`, `single` |
| A listing | `collection-{type}`, (`collection-terms`), `collection-{kind}`, `collection` |
| A term's page, or any entry's page listing what links to it | `term-{type}-{slug}`, `term-{type}`, `term`, `collection` |
| A date archive | `archive-date-{type}`, `archive-date`, `collection` |
| What a type's relation links to (such as `/recipes/cooks` or `/movies/directors`) | `related-list-{type}-{relation}`, `related-list-{relation}`, `related-list`, `collection` |
| An entry's archive under a relation (such as `/recipes/cooks/jane` or `/movies/directors/penny`) | `related-{type}-{relation}`, `related-{relation}`, `related`, then `profile` for a profile or `term` otherwise, `collection` |
| A profile's page (such as `/profiles/jane`) | `profile-{slug}`, `profile`, `collection` |
| The homepage | `home`, then whatever it shows |
| An error | `error-{status}`, `error` |
| A site with no homepage yet | `welcome` |

An entry's `template` front matter is always tried first (see
[Templates for single entries](entry-templates.md)).

## What relation pages get

On a relation's list (`/recipes/cooks`), `$entries` holds what it links
to (link each with `$target->archiveUrl($type, $page->relation->name)`), `$page->relation` is the relation, and `$entry` is its
list page (`_cooks`), when it has one. On one target's archive,
`$entry` is the page written for it (`_cooks/jane`) or else the target,
`$page->target` is always the target (a profile, for a credit),
`$page->relation` the relation, and `$entries` the entries of that type
linking to it. On a profile's page, `$entry` is the profile and
`$entries` everything crediting them.

## Kinds

`{kind}` is the kind of the entry's type: `collection` (such as posts),
`tree` (pages, and any other tree), or `profiles`. So
`single-tree.php` draws every tree's pages and `single-collection.php`
every collection's entries, whatever a site names its types. A
[type of terms](../concepts/relationships.md) (one a
classify relation files entries under, such as tags) tries `-terms`
first: `collection-terms.php` lists any such type's terms, and
`single-terms.php` draws a term served as a single entry (one whose
type has no term pages).

## The welcome page

The welcome page shows until `user/content/index.md` exists. Its
content (the next steps, and, outside production, any problems
`bin/blush doctor` would report) is Blush's own
`partials/welcome` part, so a theme's own `welcome` view can wrap it in
its markup with `$template->include('partials/welcome')`.

## Feeds and sitemaps

Feeds (`feed-rss`, `feed-atom`, `feed-json`) and sitemaps (`sitemap`,
`sitemap-index`) are templates too, Blush's own unless your theme has
them, if you need to change them.
