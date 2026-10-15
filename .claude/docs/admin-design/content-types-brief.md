# Content types: everything a type can set

> **Status:** a reference, read from the code (`Blush\Content\Type`,
> `Blush\Content\Relation`, `Blush\Field`; D-311 to D-861). It lists the
> data, not the screens.

For the design team. This is every piece of data a content type has:
each option's key, what it holds, its default, the values it takes, the
rules it's checked against, and which kinds take it. It covers what the
code allows, whether or not the admin shows it today. Where the admin
shows an option differently from how it's stored, a note says so.

A type is a record. A type the site makes is kept in
`user/data/types/{key}.json` (or the `types` table in a database). A
type from code is a PHP object a plugin makes. Both have the same
options. Every option below is shown with its JSON key.

---

## 1. Identity

| Key | Holds | Default | Rules |
|---|---|---|---|
| `name` | The type's key. In a file, it's the file's name, not a key inside it. | (required) | A lowercase letter, then lowercase letters, digits, and `_` (`^[a-z][a-z0-9_]*$`). Not `system` or `error`. Unique across every type, from the site or code. **Fixed once made**: files, addresses, capabilities, and relationships name it. |
| `kind` | `collection`, `tree`, or `profiles` | `collection` | **Fixed once made.** A site has at most one `profiles` type, and it's built in. "Taxonomy" isn't a kind; see §11. |

The type's folder isn't an option. Every type keeps its entries in
`user/content/_{name}`; the built-in pages are the content root itself.

## 2. Names

`labels` is a map. Every key is optional, and each one left out is made
from the one before it.

| Key | Holds | Default |
|---|---|---|
| `labels.singular` | "Recipe" | The key made readable (`cook_time` → "Cook time"). |
| `labels.plural` | "Recipes" | The singular made plural. |
| `labels.menu` | The name in the Content menu | The plural. |
| `labels.item` | Mid-sentence singular: "recipe" | The singular, first letter lowercased unless it's a proper noun. |
| `labels.items` | Mid-sentence plural: "recipes" | The plural, likewise. |
| `labels.newItem` | "New Recipe" | "New " + the singular in Title Case. |
| `labels.editItem` | "Edit recipe" | "Edit " + `item`. |
| `labels.searchItems` | "Search recipes" | "Search " + `items`. |

| Key | Holds | Default |
|---|---|---|
| `description` | A sentence on what it's for, shown in the admin | `""` |
| `icon` | An icon name from the admin's icon set | The kind's icon |

## 3. Place in the admin's menu

| Key | Holds | Default | Rules |
|---|---|---|---|
| `position` | Its order in the Content menu, lowest first | none (after those with one, by name) | A whole number, 0 or more. Not on profiles. |
| `under` | The type it sits under in the Content menu | none (top level) | Another type's key. One level only: that type must be at the top, and a type with types under it can't go under another. Not the type itself. Not on profiles. Affects only the menu: no address, folder, or capability follows it. |

## 4. On the site

| Key | Holds | Default | Notes |
|---|---|---|---|
| `public` | Whether entries have pages on the site at all | `true` | Off: no addresses, and it's out of the sitemap and `llms.txt` whatever those say. |
| `sitemap` | Whether entries are in `sitemap.xml` | `true` | Only while `public`. |
| `llms` | Whether entries are listed in `llms.txt` | `true` for collections and trees; `false` for profiles | Only while `public`. The admin's wizard turns it off for a taxonomy. |
| `visibility` | Whether each entry chooses a visibility: `public`, `unlisted` (an address, but in no lists), or `hidden` (no address) | `true` | Only while `public`. Off: every entry is public, and the editor hides the choice. |

## 5. Addresses

### Collections and profiles: `urls`

`urls` is a map, or `false` for no addresses at all.

| Key | Holds | Default |
|---|---|---|
| `urls.prefix` | Where its addresses start: `recipes` | The type's key. Profiles: `profiles`. |
| `urls.paths` | A map of route key → path after the prefix (below) | Each key's default path. |
| `urls.single` | A shortcut for `paths.single` | |
| `urls.collection` | A shortcut for `paths.collection` | |

A site setting (`dataTypeUrls` in `config/content.php`) can stop types
from `user/data/types` from setting `urls` at all.

**Route keys.** A type answers at each of these that applies. A path is
written after the prefix and may use letters, digits, `-`, `_`, `.`,
`/`, and `{placeholders}`. Each key's path must hold the placeholders it
needs and can hold only the ones it allows.

| Route key | Default path | Needs | May hold | When |
|---|---|---|---|---|
| `collection` | `` (the prefix) | | | Always, except on the home type |
| `collection.paged` | `page/{page}` | `page` | | Always, except on the home type |
| `collection.year` … `collection.second` | `{year}` … `{year}/{month}/{day}/{hour}/{minute}/{second}` | Each date part down to its level | | One per level of `dateArchives` |
| `collection.{level}.paged` | The same + `/page/{page}` | The same + `page` | | Likewise |
| `single` | `{name}` | `name` | `year`, `month`, `day`, `hour`, `minute`, `second`, and any taxonomy's key | Always (a taxonomy's own entries: `name` only) |
| `single.paged` | `{name}/page/{page}` | `name`, `page` | | A taxonomy's entries, and profiles |
| `collection.feed`, `.feed.atom`, `.feed.json` | `feed`, `feed/atom`, `feed/json` | | | When the type has a feed |
| `single.feed`, `.feed.atom`, `.feed.json` | `{name}/feed`, … | `name` | | A taxonomy's entries and profiles, with a feed |
| `{word}.collection` | `{word}` | | | Each relationship archive under the type (§10's `inverse.archive`) |
| `{word}.single` | `{word}/{target}` | `target` | | Likewise |
| `{word}.single.paged`, `{word}.single.feed…` | … | `target` (+ `page`) | | Likewise, feeds when the type has one |

Profiles answer only at `single`, `single.paged`, and their feeds; there's nothing at the prefix itself.

### Trees: `prefix`

A tree has no `urls`. Each entry is at its folder path under the tree's
`prefix`: `_doc/install/requirements.md` is at `/docs/install/requirements`.

| Key | Holds | Default | Rules |
|---|---|---|---|
| `prefix` | The path its pages are under: `docs` | The type's key | A path without slashes at either end. The built-in `page` tree takes none: it's the root. |

A tree has no listing page, feed, or date archives. Its folder's
`index.md` is its landing page.

## 6. Listing page (collections and profiles)

`listing` is a map, used by the type's listing page.

| Key | Holds | Default | Values |
|---|---|---|---|
| `listing.type` | The type it lists, if not itself | itself | A type's key |
| `listing.orderBy` | What it sorts by | `date` (or `position`, per `order`) | `date`, `updated`, `title`, `slug`, `author`, `position`, or a field's key. Never a file name. |
| `listing.order` | The sort direction | `desc` for dates | `asc`, `desc` |
| `listing.perPage` | Entries per page | 10 | A whole number; `0` for every entry on one page |
| `listing.query` | Other query arguments | none | A map of argument → value, such as `{"terms": {"category": "news"}}`. Can't set the four keys above. |

## 7. Feed (collections and profiles)

| Key | Holds | Default | Values |
|---|---|---|---|
| `feed` | Whether the type has RSS, Atom, and JSON feeds, and how they list | `false` | `true`, `false`, or a map: |
| `feed.categories` | The taxonomy whose entries become each item's categories | every taxonomy but authors | A taxonomy's key |
| `feed.listing` | How the feed lists entries | newest first, the site's feed limit | A `listing` map (§6) |

Trees have no feed.

## 8. Dates and order (collections)

| Key | Holds | Default | Values |
|---|---|---|---|
| `dateArchives` | How finely date archives go. A dated type gives new entries a date. | `none` | `none`, `year`, `month`, `day`, `hour`, `minute`, `second`. Each level includes the ones above it. |
| `order` | How entries sort in lists and the admin | `date` (newest first) | `date`, `position` (each entry's `position`, then title; those without one last) |
| `hierarchical` | Whether an entry may name a parent entry of the same type (as categories nest) | `false` | `true` adds a `parent` field to its entries. A loop of parents is refused. |

A tree's entries always have `position` and nest by folder.

## 9. Files (when content is kept in files)

These apply only to new entries and moved files; changing them never
renames existing files (Site Health offers to).

| Key | Holds | Default | Rules |
|---|---|---|---|
| `filename` | The pattern new files are named by | `{slug}` | Must end in `{slug}`, with a `.` before it when anything comes first. Tokens: `{date}` (`2026-10-15`), `{time}` (`153000`), `{year}`, `{month}`, `{day}`, `{hour}`, `{minute}`, `{second}`. Otherwise letters, digits, `-`, `_`, and `.`, not starting with `_` or `.`. Any kind. The admin offers the slug; the date and slug; the date, time, and slug. |
| `folders` | Folders below the type's own that files are kept in | none (flat) | One token per folder, `/` between: `{year}`, `{month}` (after `{year}`), `{initial}` (the slug's first letter or digit). No token twice. Collections and profiles only. Folders are never part of an address or an entry's key. |

## 10. Workflow and expiry

| Key | Holds | Default | Rules |
|---|---|---|---|
| `workflow` | The workflow its entries' statuses move through, and who may move them | `standard` (draft, published) | A workflow's name (the site's in `user/data/workflows`, or a plugin's). Changing it keeps entries' statuses; Site Health lists those the new workflow doesn't use. |
| `expires` | Whether entries may have an `expires` date, after which they're off the site, as a draft is | `false` | |
| `byline` | Which credit relationship names an entry's author, shown first | its only credit relationship | A credit relationship's name, when the type has more than one. Not on profiles. |

## 11. Fields

### What every entry has

Every type's entries have these built-in fields, which no other field
may reuse: `title`, `subtitle`, `slug`, `date`, `expires` (only used
when `expires` is on), `updated`, `status`, `trashed`, `visibility`
(only used when §4's is on), `summary` (alias `excerpt`), `image` (the
featured image, a media field), `template` (alias `view`), `stylesheet`,
`class`, `translation_of`, `collection`, and the Markdown body.

Then by kind and setup:

- a field for each relationship from the type (§12), under its key;
- a hierarchical collection's `parent`;
- a tree's, or a `position`-ordered collection's, `position`;
- a profile's `avatar` (a media field) and `linkable`.

`id` and `refs` are reserved: the entry's id and its links' ids.

### What a type adds: `fields`

The type's own fields come from the site's **field library**
(`user/data/fields`, §13). `fields` is the list of library fields the
type carries, in the order the editor shows them. Each item is one of:

| Item | Shape | Holds |
|---|---|---|
| A field | `"cook_time"` | A library field's key. |
| A field, changed for this type | `{"field": "cook_time", "label": "…", "description": "…", "required": true}` | The same field, asked for differently. Each of `label`, `description` (help), and `required` (required to publish) is optional and starts as the field's own. Nothing about the value changes per type. Only a top-level field changes, never one inside an Object. |
| A section | `{"section": "Timing", "description": "…", "fields": [ … ]}` | A heading over some fields in the editor's sidebar. `fields` holds field items as above. One level: no section in a section. Only presentation: it saves nothing to entries. |

Rules:

- A field is placed once per type.
- A field whose key the type already declares (a built-in field, a
  relationship's key, a plugin's registered field) is left off that
  type and reported, not an error.
- A field the library doesn't have is skipped.
- **Any type can be given fields**, even one the site can't otherwise change
  (the built-in pages, profiles, a plugin's type).

| Key | Holds | Default | Notes |
|---|---|---|---|
| `fields` | The list above | `[]` | A plugin's type may also define fields of its own in PHP; the admin shows those read-only, before the library's. |
| `closed` | Whether front matter no field declares is an error | `false` | Off: such keys stay in the file but nothing reads them. |

## 12. Relationships

Relationships are records of their own (`user/data/relations/{name}.json`,
or from a plugin), not options on a type. A type's screen lists every
relationship it's part of, from either side. Every option:

| Key | Holds | Default | Rules |
|---|---|---|---|
| `name` | Its key | (required) | Like a type's key. Unique on each source type. |
| `kind` | What it's for | `reference` | `classify` (filed under a taxonomy's entries), `credit` (the profiles an entry credits), `reference` (any other entries), `parent` (nesting within a type), `translation` (built in). |
| `from` | The source types, whose entries hold the link | every type | Type keys. Empty means every type, including later ones. |
| `to` | The target types | (required) | Type keys. A credit's target is the profiles type. |
| `field` | The front matter key it's written under | its name | Not `id` or `refs`, nor a built-in field. |
| `aliases` | Other keys it's read from | `[]` | |
| `label` | What people call it ("Actors") | its name made readable | |
| `singular` | What one target is called ("Cook") | made from the label | |
| `multiple` | Whether a source may link to several targets | `true` | Never for `parent` or `translation`. |
| `ordered` | Whether the targets' order means something (a lead) | `false`; always for a credit | |
| `min` | The fewest targets a published entry needs (drafts may have fewer) | `0` | `"required": true` is `min` 1. |
| `max` | The most targets | any number | Only when `multiple`. |
| `create` | Whether new targets can be made as they're typed (a tag) | `false` | |
| `symmetric` | Whether each link holds from both ends (related posts) | `false` | |
| `defaults` | Targets filled in when an entry is made | `[]` | Written once, never applied when read. |
| `control` | How the editor picks targets | its shape's | `tree` (a nesting taxonomy's checkboxes), `tokens` (chips with a search that can make new ones), `people` (credited profiles in order), `select` (one, from a list), `cards` (chips with a search showing each entry's image, status, and date). One its shape can't draw falls back. |
| `translations` | How a translation's links relate to its original's | `fallback`; `add` for a credit | `fallback` (its own, else the original's), `add` (both), `own` (its own only) |
| `inverse` | The targets' side, or `false` for none shown | a classify or credit relationship's targets list what's linked to them | A map: |
| `inverse.label` | What a target calls the list ("Acted in") | the source type's plural | |
| `inverse.page` | Whether a target's own page lists them | `true` for classify and credit | |
| `inverse.archive` | A word for an archive under each source type (`/posts/authors/jane`), or `false` | `false`; a credit's name | A slug. Adds §5's `{word}.…` route keys. |
| `inverse.types` | Which source types a target's page lists | every one | |
| `inverse.max` | How many entries may link to one target | any number | At least 1. |
| `inverse.listing` | How a target's page lists them | | A `listing` map (§6). |

An entry never links to itself. A relationship naming a type has to be
removed before the type can be deleted.

## 13. The field library

The fields a type's `fields` list places. Each is `user/data/fields/{key}.json`, one
field per file, the same on every type that carries it.

| Key | Holds | Default | Rules |
|---|---|---|---|
| (the file's name) | The field's key, its front matter key on every type | (required) | `^[a-z_][a-z0-9_]*$`, not `new`. Fixed once made in the admin. |
| `type` | What its value is | the control's, else `text` | Below. |
| `label` | Its name for people | the key made readable | |
| `description` | Help, under it | `""` | |
| `required` | Whether a value is needed to publish | `false` | A type can change it (§11). |
| `default` | The value used when none is given | none | |
| `aliases` | Older keys it's still read from | `[]` | |
| `control` | How it's entered: `{"name": "…", …its options}` | the type's default control | Below. |

**Field types** and their own options:

| `type` | Holds | Its options |
|---|---|---|
| `text` | A line of text | `format`: `url`, `email`, or `icon` |
| `markdown` | Formatted text | |
| `date` | A date and time | `time`: `false` for the day only |
| `bool` | true or false | |
| `number` | A number | `integer`, `min`, `max`, `step` |
| `enum` | One of a set of values | `options`: values, or `{"value", "label"}` pairs |
| `list` | Several values | `item` (a field definition for each value; text by default), `min`, `max` |
| `media` | A media file, by its id | `kind`: `image`, `video`, `audio`, `document`, `file` |
| `slug` | A URL-safe name | |
| `object` | Several fields kept together under its key (`seo.title`) | `fields` (definitions, as a list with names or a map), `closed` |

**Controls**, by the type they store (the first for a type is its
default):

| `control.name` | Stores | Its options |
|---|---|---|
| `object` (Object) | `object` | |
| `list` (List) | `list` of `object` items, or of one-line values | `itemLabel` ("Ingredient"), `summary` (keys that sum up an item) |
| `text`, `textarea` | `text` | `placeholder` |
| `markdown` | `markdown` | `placeholder` |
| `link` | `text` with `format: url` | `placeholder` |
| `email` | `text` with `format: email` | `placeholder` |
| `select`, `radios` | `enum` | |
| `checkboxes`, `multiselect` | `list` of `enum` items | |
| `toggle` | `bool` | |
| `number` | `number` | `placeholder` |
| `date` | `date` | |
| `image`, `video`, `audio` | `media` of that kind | |
| `file` | `media` of any other kind, or any | |
| `icon` | `text` with `format: icon` | |
| `code` | `text`, `slug`, or `media` | |
| `readonly` | anything | |

A saved field's control can change only to another storing the same
value (Select ↔ Radios, Text ↔ Textarea, Checkboxes ↔ Multi-select).
An Object's fields can't be Objects in the admin; files may nest
deeper. Moving a field into or out of an Object moves entries' values.

## 14. When a type is made

The admin can do these along with making a type. They aren't options on
the type:

| Choice | What it makes | Notes |
|---|---|---|
| Index page | An entry at the type's prefix, titled with its plural name, pinned at the top of its list | Given once and kept; edited like any entry. A tree's is its folder's `index.md`. |
| Credits authors | Adds the type to the `authors` credit relationship's `from`, making the relationship if there's none | Only when the site has profiles. |
| Authors list page | A page introducing the list of the type's authors | For each relationship archive, the same choice: `listPages`. |
| Taxonomy | A collection with `order: position` and `llms: false`, plus a `classify` relationship named after it, `to` the new type, `from` the types it files (none: every type) | Not a kind of its own. The admin shows it as a third choice beside Collection and Tree. |

## 15. Who can change what

**By where a type comes from:**

| Origin | What can change |
|---|---|
| The site (`user/data/types`) | Every option but `name` and `kind`. It can be deleted; its entries stay in their folder, unlisted. |
| A plugin: a collection, or a tree in a folder | Every option but `name` and `kind`, saved as a file of only the differences. **Reset** removes that file. It can't be deleted. |
| The built-in pages, profiles, or another plugin type that can't be changed | Only `fields`, `position`, and `under` (not `position` or `under` for profiles). |

**Site settings that lock things** (`config/content.php`):

- `dataTypes` off: no type is made or changed in the admin.
- `dataTypeUrls` off: `urls` can't be set from `user/data/types`.

**Capabilities:**

- Making, changing, and deleting types, relationships, and library
  fields needs `site.settings`.
- Each type also has its own: `content.{key}.{action}`, where the actions
  are `create`, `edit`, `edit.others`, `publish`, `publish.others`,
  `delete`, and `delete.others`. A role that grants
  `content.*.{action}` covers every type, including ones added later.

**What a change never does:**

- It never moves or renames existing files.
- A changed address moves pages; redirects for the old ones are added separately.
- A changed workflow keeps entries' statuses.

Every save is checked against every other type and relationship, and a
change that doesn't fit is refused whole, with the reason.

## 16. Options by kind

| Option | Collection | Tree | Profiles |
|---|---|---|---|
| `labels`, `description`, `icon` | ✓ | ✓ | ✓ |
| `position`, `under` | ✓ | ✓ | |
| `public`, `sitemap`, `llms`, `visibility` | ✓ | ✓ | ✓ |
| `urls` | ✓ | | ✓ |
| `prefix` | | ✓ | |
| `listing`, `feed` | ✓ | | ✓ |
| `dateArchives`, `order`, `hierarchical` | ✓ | | |
| `filename` | ✓ | ✓ | ✓ |
| `folders` | ✓ | | ✓ |
| `workflow`, `expires` | ✓ | ✓ | ✓ |
| `byline` | ✓ | ✓ | |
| `fields`, `closed` | ✓ | ✓ | ✓ |

## 17. A type with most of its options

`user/data/types/recipe.json`:

```json
{
	"labels": { "singular": "Recipe", "plural": "Recipes", "menu": "Recipes" },
	"description": "Dishes worth making again.",
	"icon": "coffee",
	"position": 3,
	"urls": { "prefix": "recipes", "paths": { "single": "{year}/{name}" } },
	"listing": { "orderBy": "title", "order": "asc", "perPage": 20 },
	"feed": { "categories": "cuisine" },
	"dateArchives": "year",
	"order": "date",
	"hierarchical": false,
	"filename": "{date}.{slug}",
	"folders": "{year}",
	"public": true,
	"sitemap": true,
	"llms": true,
	"visibility": true,
	"workflow": "editorial",
	"expires": true,
	"byline": "cooks",
	"fields": [
		"difficulty",
		{ "field": "servings", "required": true },
		{
			"section": "Timing",
			"description": "In minutes.",
			"fields": ["prep_time", { "field": "cook_time", "label": "Cook time (oven)" }]
		}
	],
	"closed": false
}
```
