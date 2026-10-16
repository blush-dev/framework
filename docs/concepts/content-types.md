# Content types

Out of the box, every entry is a **page**. That's enough for a simple
site. When you want a blog, a portfolio, or a recipe box, where entries
are listed, dated, tagged, or given their own fields, define a **content
type**.

Each content type owns a folder in `user/content/`: its name after an
underscore (`_recipe` for `recipe`). Entries in that folder belong to
it, and its `index.md` becomes the listing page. Every type is kept this
way; a type never names its own folder. The underscore keeps type
folders apart from your page folders. A type's addresses come from its
[URL prefix](file-names-and-urls.md#custom-urls), which is its name unless you set one: `_recipe/`
is served at `/recipe`, or at `/recipes` with the prefix `recipes`.

For a worked example, see [Building a blog](building-a-blog.md).

## Three kinds of type

Every content type is one of three kinds:

- **Collection:** entries that are listed, such as posts, recipes, or
  projects. A collection has a listing page, and it can have a feed and
  date archives (see [Archives and feeds](archives-and-feeds.md)). Tags,
  categories, and series are collections too: a
  **taxonomy** (as the admin calls it) is a collection that other
  entries are filed under through a [relation](relationships.md),
  and each of its entries gets a page listing the entries filed under
  it.
- **Tree:** entries that nest by folder, each served at its path under
  the tree's prefix.
  The built-in `page` type is a tree: it holds every entry that isn't in
  another type's folder, and `about/team.md` is a subpage of `about.md`
  (or `about/index.md`). You can add [trees of your own](trees.md), such as
  docs.
- **Profiles:** the built-in `profile` type, the people your entries
  credit. A site has one.

## Built-in types

- **`page`** (tree): every entry that isn't in another type's folder.
- **`profile`** (profiles): people, in `user/content/_profile/`. Each
  file is one person: the title is their public name, `subtitle` a line
  under it (such as "Food editor"), `avatar` a portrait from
  `user/media`, `linkable: false` to lock it against being linked to
  an account (see [Accounts](accounts/README.md)), and the body their bio. Each profile has a page at
  `/profiles/jane`. Entries credit them through the
  [credit relations](credits-and-profiles.md) of their type, such as
  `authors: jane`. Every profile is a file: a person credited with no
  file is left out of bylines and has no page. `content:lint` reports
  it, and `bin/blush content:terms --write` writes the missing files. An
  [account](accounts/README.md) can be linked to a profile.

## Two ways to define a type

**In JSON** (no PHP needed): create a file in `user/data/types/`
named after the type, or let the admin's **New Content Type** write it.
Use `kind` to pick the kind; it's `collection` if you leave it out.
`user/data/types/recipe.json`:

```json
{ "urls": { "prefix": "recipes" } }
```

Its entries go in `user/content/_recipe/` and are served under
`/recipes`. The file's name is the type's name; the file never says it, and one
naming another type is refused. When the admin saves a type, it adds an
`"id"` at the end of its file: the type's record id, which stays the
same from then on. A file without one works just as well. Relations in
`user/data/relations/` work the same way. On the
[SQLite driver](../running/sqlite.md), types and relations
are kept in the database instead.

**In a plugin**, in PHP, when the types belong with code you install.
Each kind is its own class (`Collection`, `Tree`, or `Profiles`), which
gives you editor autocomplete and type checking:

```php
new Collection('recipe', urls: new TypeUrls(prefix: 'recipes'))
```

See [Content types from a plugin](../features/content-types.md)
for the class that returns them.

Both do the same thing, and every option below works in each. (In
JSON, use the option names as keys.) A type's name uses lowercase letters,
digits, and underscores. It can't be `system` or `error`: `_system`
and `_error` are folders your pages keep.

If two places define the same type, a plugin's type replaces a
built-in one. A JSON type may replace a built-in type; one named for a
plugin's type changes it, as [below](#changing-a-type-from-code). When two plugins define the
same type, the first plugin's is used and the other's is left out, and
Site Health says so (see [Conflicts](../plugins/conflicts.md)).

Which to pick:

- **JSON** keeps a type with your content, so a copy of `user/` carries
  it along. It's the only kind the [admin](../admin/README.md) can
  create and edit.
- **A plugin** keeps it with a feature you can reuse or version on its
  own.

### Changing a type from code

A file in `user/data/types/` named after a collection or
[tree](trees.md) from a plugin changes that type rather than
defining a new one. Each option it sets replaces the code's, and the
rest stay as the code has them. For recipes defined in a plugin,
`user/data/types/recipe.json`:

```json
{
	"description": "Dinners worth making twice.",
	"feed": false
}
```

This is what the admin writes when you edit such a type, and
it keeps only what differs from the code. A file like this can't change the
type's kind, and the `page` type and the profiles type from
code can't be changed this way. Delete the file (or use **Reset the
Type** in the admin) to go back to the plugin's definition. With `dataTypes` off, these files aren't read either.

### Moving a type into its folder

Types once named their own folder (`"folder": "recipes"`, or 1.x's
`"path"`). A file in `user/data/types/` that still does is read in `_`
and its name, with the folder's path as its URL prefix, so the admin
keeps working. Its entries aren't found until they're moved, since
they're still in the old folder. To move them, run
`bin/blush content:type-folders` to see what would change, then
`bin/blush content:type-folders --write`, or use **Type Folders** on
Site Health in the admin. Each type's files move into `_` and its name,
and its file is written without the folder: a folder pattern after it
(`_posts/{year}`) becomes `folders`, and a collection or tree whose
addresses came from the folder gets that path as its `prefix`, so no
address changes. Another type's folder inside the old one stays for
that type's own move.

A type from a plugin that names its folder stops the site from loading,
saying what to change: take out `folder` (and move its files), and give
it a `prefix` if its addresses came from the folder.

## Names, descriptions, and icons in the admin

The [admin](../admin/README.md) names each type in its menu, headings, and
buttons with its `labels`. Each label is made from the ones above it,
starting from the type's name, so set only the ones that come out
wrong:

| Label         | Made from  | For `literary_form`   | Where the admin shows it                       |
|---------------|------------|-----------------------|------------------------------------------------|
| `singular`    | The name   | Literary form         | Field headings, type menus                     |
| `plural`      | `singular` | Literary forms        | The list's heading, the page title             |
| `menu`        | `plural`   | Literary forms        | The admin's navigation                         |
| `item`        | `singular` | literary form         | Mid-sentence: "Create the first literary form" |
| `items`       | `plural`   | literary forms        | Mid-sentence: "3 literary forms"               |
| `newItem`     | `singular` | New Literary Form     | The New button and screen, in Title Case       |
| `editItem`    | `item`     | Edit literary form    | The editor's title                             |
| `searchItems` | `items`    | Search literary forms | The list's search field                        |

`plural` adds "s" ("es" after s, x, z, ch, or sh, and "ies" for a y
after a consonant). `item` and `items` lowercase the first letter,
unless the first word is an acronym or has another capital ("FAQ",
"HTML snippet" stay as they are).

Set `plural` when the name doesn't make a good English plural, the
mid-sentence names for a proper noun, and any of them to call the type
something else. `menu` shortens a long name in the navigation only, such
as "Forms" for literary forms listed under Literature; the navigation is
sorted by it:

`user/data/types/person.json`:

```json
{
	"labels": { "plural": "People", "newItem": "Add someone" }
}
```

`user/data/types/literary_form.json`:

```json
{
	"order": "position",
	"labels": { "menu": "Forms" }
}
```

In PHP, `TypeLabels` takes `singular` first and the rest by name:

```php
use Blush\Content\Type\TypeLabels;

new Collection('person', labels: new TypeLabels('Person', plural: 'People'))
```

A label can also be a message, so it's translated (see
[Translations in code](../features/translations.md)). Give it as it comes from `t()`, without
casting it, and it's shown in the language of whoever reads it:

```php
new TypeLabels(
	$this->messages->t('types.recipe.singular', 'Recipe'),
	plural: $this->messages->t('types.recipe.plural', 'Recipes')
)
```

Labels left out are made from the singular in English ("New Recipe",
"Search recipes"), so a translated type sets each one. A `Tree` named
`page` and `Profiles` named `profile` are the exception: without
labels of their own, they have Blush's, translated, as the built-in
types do.

`description` says what the type is for, in a sentence. The admin shows it
on the type's screen and on its list while it's empty. `icon` names an
icon to show the type with in the admin's menu, from the icons the `icon`
block offers (`bin/blush icon:list`), such as `film` or
`book-open`. Without one, the type gets its kind's icon.

`user/data/types/recipe.json`:

```json
{
	"description": "Dishes we cook at home, with what goes in them.",
	"icon": "notebook-pen"
}
```

### Where a type sits in the admin's menu

The admin's **Content** menu lists your types alphabetically by their
menu names. `adminPosition` puts a type in an order of your own: types
with one come first, lowest first, then the rest alphabetically.
`adminUnder` names the type it sits under in the menu, such as a Cast
type under Movies:

`user/data/types/cast.json`:

```json
{
	"labels": { "plural": "Cast" },
	"adminPosition": 2,
	"adminUnder": "movie"
}
```

The menu goes one level deep: a type can't sit under one that's under
another. Types under a type are ordered among themselves the same way.
These settings change only the menu: not addresses, folders, or who can
edit what. A type someone can't edit isn't in their menu, so the types
under it are at the top level for them. Profiles are listed with
accounts, so they take neither setting.

You don't have to write these by hand: each type's screen under
**Structure → Content Types** has an **In the Menu** panel with **Sits
Under** and **Position**. It's there for every type, including Pages
and types from plugins, even ones whose other settings can't be changed
in the admin. For a type defined in code, the file in `user/data/types/`
holds only what you've changed, and resetting the type keeps its place.

## All type options

No type takes a folder: each is kept in `_` and its name (`page`, at
the content root). An older definition naming one (`folder`, or 1.x's
`path`) is refused from a plugin; one in `user/data/types/` is read in
`_` and its name until it's moved (see
[Moving a type into its folder](#moving-a-type-into-its-folder)).

Every kind takes these:

| Option              | Default                                                        | What it does                                                     |
|---------------------|----------------------------------------------------------------|------------------------------------------------------------------|
| `labels`            | Made from the name                                             | [Names in the admin](#names-descriptions-and-icons-in-the-admin) |
| `description`       |                                                                | What the type is for, in a sentence                              |
| `icon`              | Its kind's                                                     | An icon for the admin, by name                                   |
| `public`            | `true`                                                         | Whether the type is visible on the site at all                   |
| `sitemap`           | `true`                                                         | Whether entries appear in the sitemap                            |
| `llms`              | `true` for collections and trees, `false` for profiles         | Whether entries are listed in `llms.txt` (see [Configuration](../reference/configuration/README.md)). Their Markdown copies stay either way |
| `fields`            |                                                                | The library fields it carries ([A type's fields](custom-fields.md#a-types-fields)) |
| `closed`            | `false`                                                        | Whether front matter no field declares is an error ([Custom fields](custom-fields.md)) |
| `filename`          | `{slug}`                                                       | How new entries' files are named ([Naming new files](file-names-and-urls.md#naming-new-files)) |
| `image`, `summary`, `subtitle`, `position`, `visibility`, `expires` | `false` | The optional built-in fields its entries have ([Supports](#supports)) |
| `workflow`          | `standard`                                                     | The statuses its entries move through, and the transitions between them, by name. Blush has `standard` (draft and published); plugins add more (see [Workflows](../features/workflows.md)). A name there isn't uses `standard`, and `content:lint` says so |

Collections and trees also take these:

| Option     | Default                    | What it does                                                                            |
|------------|----------------------------|-----------------------------------------------------------------------------------------|
| `byline`   | Its only credit relation   | The [credit relation](credits-and-profiles.md) its byline uses, by name, when it has several |
| `adminPosition` | None (alphabetical)   | [Its order in the admin's menu](#where-a-type-sits-in-the-admins-menu), lowest first    |
| `adminUnder` | None (the top level)     | [The type it sits under in the admin's menu](#where-a-type-sits-in-the-admins-menu)     |

Trees also take this:

| Option   | Default   | What it does                                                          |
|----------|-----------|-----------------------------------------------------------------------|
| `prefix` | Its name  | The path its pages are served under ([Trees](trees.md)); not for `page` |

Collections and the profiles type also take these:

| Option    | Default       | What it does                                                                                                                      |
|-----------|---------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `urls`    | Standard URLs | `false` for no URLs of its own (the admin's **Routes** off), or [custom URLs](file-names-and-urls.md#custom-urls)                 |
| `listing` |               | How the listing page lists entries ([Listing entries](archives-and-feeds.md#a-types-listing-page))                               |
| `feed`    | `false`       | RSS, Atom, and JSON feeds: `true`, or a `TypeFeed` with `categories` (the [type of terms](relationships.md) used for each item's categories) and `listing` |
| `folders` |               | The [folders its files are kept in](file-names-and-urls.md#folders-for-many-files) inside its own: `{year}`, `{year}/{month}`, or `{initial}` |

Only collections take:

| Option         | Default     | What it does                                                                                     |
|----------------|-------------|--------------------------------------------------------------------------------------------------|
| `dateArchives` | `none`      | Date archives: `year`, `month`, `day`, `hour`, `minute`, or `second`                             |
| `hierarchical` | `false`     | Whether an entry may name a `parent` entry ([Nesting and order](relationships.md#nesting-and-order)) |
| `order`        | `date` | `date` (newest first) or `position` (lowest first, then title) ([Nesting and order](relationships.md#nesting-and-order)) |

### Supports

Besides `title`, `slug`, `status`, `date`, and `updated`, which every
entry has, a type chooses which of core's other fields its entries
have, each switched on in its file (or under **Supports** on its
screen). Each is off unless it says `true`:

| Option       | Adds                                                                      |
|--------------|---------------------------------------------------------------------------|
| `image`      | `image`, a featured image for lists, feeds, and shared links              |
| `summary`    | `summary` (`excerpt` too), a short description for lists, feeds, and search |
| `subtitle`   | `subtitle`, a line under the title                                        |
| `position`   | `position`, a hand-picked order among siblings                            |
| `visibility` | `visibility`, each entry public, unlisted, or hidden ([Visibility](status-and-visibility.md#visibility)) |
| `expires`    | `expires`, a date after which an entry is off the site ([Expiry](status-and-visibility.md#expiry)) |

```json
{ "image": true, "summary": true, "position": true, "visibility": true }
```

A field that's off isn't declared, so a value for it in a file stays
there unread, and the editor doesn't show it. The admin's **New Content
Type** starts with `image`, `summary`, and `visibility` on, and
`position` for trees and nesting types. The built-in pages have
`image`, `summary`, `position`, and `visibility`; profiles have
`subtitle` and `visibility`. A plugin's type sets its own (`new
Collection('recipe', image: true, summary: true)`).

## Turning off a built-in type

If you don't want profiles, in `config/content.php`:

```php
return new ContentConfig(disabled: ['profile']);
```
