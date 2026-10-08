# Writing content

Everything visitors read lives in `user/content/`. Each file is an
**entry**: a page, a post, a tag, and so on. You write entries in Markdown
with a small block of settings at the top, called front matter.

## Your first page

Create `user/content/contact.md`:

```markdown
---
title: "Contact me"
---

Send me an email at **hello@example.com**.
```

Visit `/contact` and it's there. In development, Blush picks up new and
changed files on the next request. (In production you publish changes
instead; see [Going live](going-live.md).)

## How files become URLs

| File | URL |
|---|---|
| `user/content/index.md` | `/` (the homepage) |
| `user/content/about.md` | `/about` |
| `user/content/about/index.md` | `/about` (same page, as a folder) |
| `user/content/about/team.md` | `/about/team` |

A few rules make the file names flexible:

- **In a collection (types of terms, such as tags, included),
  everything before the last `.` is ignored.** Use it to keep files in order on disk: `01.intro.md`,
  `02.setup.md`, or `2026-09-26.hello.md` become `intro`, `setup`, and
  `hello`. Listings never sort by file name: a collection lists newest
  published first, and pages and terms by `position`, then title. Pages and profiles
  don't take these order prefixes: name them as their URLs read
  (`about.md`). A prefixed page still works, but `content:lint` reports
  it as an error, because a prefix orders nothing among pages and its
  folder wouldn't nest under it. The exception is a prefix their type's
  own file name pattern gives a file; folders never take one. Hidden
  files (`_`-prefixed, or in a `_` folder) are left alone, since they
  have no address. Any type can name the files it creates for you by a
  pattern ([Naming new files](content-types.md#naming-new-files)).
- **A collection's entries are files in its folder**, never folders of
  their own ([Collections are flat](content-types.md#collections-are-flat)).
- **`index.md` is its folder's page.** `about/index.md` is the page at
  `/about`. (Not in a collection, which is flat.)
- **Set `slug:`** in front matter to choose the URL name yourself.
- **Folders nest pages.** `about/team.md` is a subpage of the `about`
  page, which themes can use for breadcrumbs and lists of subpages.

Folders can also hold a whole [content type](content-types.md), like a
blog whose posts are listed at `/blog`. A type's folder starts with an
underscore (`_blog`) so it stands apart from your page folders. Plain
folders of pages need no setup.

## Front matter

Front matter is YAML between two `---` lines at the top of the file.
Every entry needs an `id` (see [Ids](#ids)); otherwise only `title` is
needed in practice. The built-in keys:

| Key | What it does |
|---|---|
| `title` | The entry's title |
| `subtitle` | A line shown under the title |
| `slug` | The URL name, instead of the file name |
| `published` | The publish date, such as `2026-09-26 09:00:00 -05:00`. A date in the future schedules the entry. (`date` works too.) |
| `updated` | When it last changed; defaults to `published`, then the file's modified time |
| `status` | `published` (the default), `draft`, or `trash`. The admin's **Move to trash** sets `trash`, which takes the entry off your site |
| `trashed` | When the entry was moved to the trash; set with `status: trash`, and removed when it's restored |
| `visibility` | `public` (the default), `unlisted`, or `hidden` |
| `summary` | A short Markdown summary for listings and feeds. Without one, the first 50 words are used. (`excerpt` works too.) |
| `image` | A featured image |
| `authors` | One author or a list, by profile slug (`author` works too), in types the `authors` relation credits. A type can credit people under other names too, such as `cooks` (see [Crediting people](content-types.md#crediting-people)) |
| `position` | A page's or term's place among its siblings, a whole number, lowest first. Those without one follow, by title. Only pages (and other [trees](content-types.md#trees)) and collections that [nest or are ordered by position](content-types.md#nesting-and-order), such as categories and tags, have it |
| `redirect_from` | Old URLs that should redirect here (see below) |
| `translation_of` | For a translation, the id of the entry it translates, which links them whatever their file names (see [Translations](#translations)) |
| `template` | The theme template to use, such as `single-wide` (`view` works too) |
| `layout` | The theme layout to use, by its name in the theme's `layouts/` folder (`wide`, or `shells/wide` for `layouts/shells/wide.php`) |
| `class` | Extra CSS classes for the page's `<body>` |
| `stylesheet` | An extra stylesheet for this page |
| `collection` | List other entries on this page (see [Content types](content-types.md#listing-entries)) |
| `id` | The entry's id, a UUID that never changes (see [Ids](#ids)). Blush writes it, last |
| `refs` | The ids of the entries this one names, such as its terms and authors (see [Links between entries](#links-between-entries)) |

Any other key you add is kept and available to your theme. [Relations](content-types.md#terms-and-relationships) add
their own keys too, such as `tag: [php, cms]`.

A fuller example:

```markdown
---
title: "Launch day"
subtitle: "Two years in the making"
published: 2026-10-01 09:00:00 -05:00
authors: jane
tag: [news, releases]
summary: "We're live. Here's what's new."
image: /media/launch.jpg
---

Today's the day...
```

## Drafts, scheduling, and hiding

| You want to... | Do this |
|---|---|
| Keep an entry unpublished | Add `status: draft`, or put it in a `_drafts/` folder |
| Publish it later | Set `published` to a future date. It goes live by itself at that time. |
| Reach it only by its link | Add `visibility: unlisted`. It has a URL, but no listing, feed, or sitemap shows it. |
| Hide it completely | Start its file name with `_` (`_notes.md`), or add `visibility: hidden` |
| Throw it away, but keep it for now | Add `status: trash` (the admin's **Move to trash** does this). It's off your site, and the admin lists it under **Trash** until you restore it or delete it. |

A `_` at the start of a folder name hides everything inside it.

## Translations

To write your site in more than one language, list the other languages
in `config/app.php`. Your site's `locale` is the default language; the
others are named by a short code:

```php
return new AppConfig(
	locale: 'en_US',
	languages: [
		'fr'    => ['locale' => 'fr_FR', 'label' => 'Français'],
		'pt-br' => 'pt_BR'
	]
);
```

Codes are lowercase, with hyphens (`fr`, `pt-br`). Then translate a file
by copying it beside the original with the code before the extension:

| Original | French translation | French URL |
|---|---|---|
| `about.md` | `about.fr.md` | `/fr/about` |
| `about/index.md` | `about/index.fr.md` | `/fr/about` |
| `_posts/2026-10-04.hello.md` | `_posts/2026-10-04.hello.fr.md` | The post's URL, under `/fr` |
| `topics/music.md` | `topics/music.fr.md` | `/fr/topics/music` |

A translation can be a plain file or a folder, whatever its original
is: `about/index.md` and `about.fr.md` are translations of each other.

A translation can also have a name of its own, when it gives the id of
the entry it translates in `translation_of`. It still needs its
language's code before the extension:

```yaml
---
# _posts/printemps.fr.md, a translation of _posts/2026-10-04.hello.md
title: Bonjour
translation_of: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74
id: 0199b6e3-1a2b-7c41-8d2e-6b9f0c3b1e75
---
```

`translation_of` names the original (the file without a language code),
of the same type. `content:lint` reports one that names no entry, a
translation, another type's entry, or that's on a file without a
language code.

The default language keeps its URLs; every other language's are under
its code. Give a translation its own `slug` to translate its URL too:
`about.fr.md` with `slug: a-propos` is at `/fr/a-propos`. Pages inside a
translated page's folder use its translated slug: with that,
`about/biography.fr.md` (`slug: biographie`) is at
`/fr/a-propos/biographie`. A folder whose page isn't translated keeps
its name.

### Pages that aren't translated

`untranslated` in `config/app.php` (or **Untranslated pages** on the
admin's General settings) says what another language does with an
entry that has no translation in it:

| Setting | `/fr/contact` without `contact.fr.md` | French lists |
|---|---|---|
| `'hide'` | Not found | Only French entries |
| `'redirect'` (the default) | Sends readers to `/contact` | Only French entries |
| `'include'` | Sends readers to `/contact` | French entries, and the originals of the rest |

The redirect is temporary, so `/fr/contact` works as soon as you write
`contact.fr.md`. A French translation that's still a draft counts as
missing. A French address that uses the original's name for a page that
is translated (`/fr/about` for `about.fr.md` with `slug: a-propos`)
sends readers to the translation. With `'include'`, an untranslated
entry is listed under its original's title and links to the original's
address; one that's translated is listed once, in French. Blush never
shows the original's text at the French address, which would be the
same page at two addresses.

With `'include'`, `/fr`, the French collections, topics, and date
archives, and the lists directives and templates make on French pages
include the originals. Otherwise each language lists only its own
entries. A French page is shown in French: its dates, `<html
lang="fr-FR">`, and the theme's text when the theme is translated. A
page in a language written right to left, such as Arabic or Hebrew,
has `dir="rtl"`, so it reads right to left.
Date archive titles are written the language's way too
(`3 de diciembre de 2025`). Directives follow the page as well, in
templates and in Markdown: a
`::acme/recent-posts` in `about.fr.md` lists the French posts, and
`time`, `progress`, `meter`, and `file` write their dates and numbers
the French way (`1 250,5`, `62 %`).

Each page tells search engines about its versions in other languages
with `hreflang` links in its `<head>`: a page's published translations,
and for the homepage, collections, topics, and date archives, the same
page in each language that has entries for it. The default language's
version is also the `x-default`. A page in only one language has none.

When you translate a topic or tag, keep the original's name in your
entries' front matter (`category: music`, not `musique`); the French
entries still find the French topic. The same goes for a topic's
`parent`: write the original's name, and the French topic's parent is
its French translation.

A few things don't work in other languages yet:

- Feeds, sitemaps, `llms.txt`, profiles, and author archives are in the
  default language only.
- The admin shows and edits only the default language's entries; edit
  translations as files.

`bin/blush content:lint` warns about a file named with the default
language's code beside the original (`about.en.md` next to `about.md`).

## Error pages

Create `user/content/_errors/404.md` to write your own "not found" page:

```markdown
---
title: "Page not found"
---

Sorry, there's nothing here. Try the [homepage](/).
```

The same works for other errors, such as `_errors/500.md`. Without one,
the theme shows a generic message.

## Redirects

When you move or rename an entry, list its old URLs in `redirect_from`:

```yaml
redirect_from:
  - /old-name
  - /2019/05/old-name
```

For redirects that aren't tied to one entry, create
`user/data/redirects.yaml` (or `.json`):

```yaml
# old path: new path (301, permanent)
/old-about: /about

# Placeholders carry parts of the path across.
/news/{name}: /blog/{name}

# A temporary redirect.
/sale:
  to: /shop
  status: 302
```

Redirects apply only when nothing else answers the URL, so they never hide
a real page.

## Markdown

Blush uses CommonMark with the GitHub extras: tables, strikethrough, task
lists, autolinks, and footnotes. On top of that:

- **An image on its own line becomes a `<figure>`**, and its title becomes
  the caption:

  ```markdown
  ![A sunflower.](/media/sunflower.jpg "My favorite flower")
  ```

  Images on lines one after another, as in a gallery, are a figure each,
  with no line breaks between them. Images side by side on one line, or
  with text, stay in their paragraph.

  To caption something else, such as a table or a code block, wrap it in
  a [figure](directives.md#built-in-directives): `:::figure[Caption]` …
  `:::`.

- Images from your media folder get their `width` and `height`
  automatically.
- **Definition lists:** a term, then its definition on the next line
  after a `:`:

  ```markdown
  Blush
  : A flat-file CMS.
  ```

  With attributes on, `{.glossary}` on a line of its own above the list
  gives the `<dl>` a class, and `{#blush}` at the end of a term or
  `{.note}` at the end of a definition gives the `<dt>` or `<dd>` one.

- **Highlighting:** `==text==` marks text as highlighted (`<mark>`).
- **Mentions:** `@jane` links to the profile whose slug is `jane`, once
  it's published. A name that isn't anyone's, an address like
  `me@example.com`, and code stay as written. The `@` and the name are
  spans of their own, so a theme can style them apart:

  ```html
  <a class="mention" href="/profiles/jane"><span class="mention__at">@</span><span class="mention__name">jane</span></a>
  ```
- **Smart punctuation:** straight quotes become curly ones, `--` an en
  dash, `---` an em dash, and `...` an ellipsis. Code stays as written.
- **Heading anchors:** each heading gets a link to itself (`#`), shown
  by the default theme when the heading is hovered.
- **Line breaks:** a line break inside a paragraph stays one (`<br>`).
- **Spans:** text in brackets followed straight away by attributes,
  `[text]{.class #id}`, becomes a `<span>` with them, for a class or id
  on a few words. A link (`[text](/url){.class}`) or a word with a link
  reference definition stays a link, with the attributes on it.
- Links that start with `/` become full URLs, so they still work in feeds.
- Raw HTML is allowed, unless your site filters it. In the admin, who may
  add it is up to their role (see
  [Capabilities](accounts.md#capabilities)).

Mentions, smart punctuation, heading anchors, figures, and raw HTML can
be changed on the [Writing settings screen](admin.md#settings), and
more in `config/markdown.php`; see
[Configuration](configuration.md#markdown).

### Directives

Directives add richer blocks to your writing:

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::

::embed[Our launch video]{url="https://youtu.be/..." title="Launch video"}
```

`callout`, `gallery`, `figure`, and `embed` work in every theme, and
plugins can add more. In the admin's editor, they're called blocks. See
[Directives](directives.md) for the syntax, every built-in directive,
and making your own.

## Ids

Every entry has an `id`: a UUID in its front matter that stays the same
when the entry is renamed or moved, so other things can point at it.
Blush writes one, as the last key, whenever it creates an entry (in the
admin, with `content:new`, or as a copy), and adds one when the admin
saves a file that doesn't have it:

```yaml
---
title: Hello, World
published: 2026-10-05 09:00:00 -05:00
id: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74
---
```

You don't need to write it yourself. For files you add by hand, or
content from before ids, run:

```sh
bin/blush content:ids          # list files missing an id, and ids files share
bin/blush content:ids --write  # give each file missing one a new id
```

**Site Health** in the admin does the same, under Content Files. A copied file shares its
original's id, which `content:lint` reports as an error. Tell Blush
which file keeps it, and the others get new ones:

```sh
bin/blush content:ids --keep=blog/2026-10-05.hello.md
```

Don't edit an id by hand, and never give two files the same one. A file
without a valid id still shows on the site, but `content:lint` reports
it as an error. Types can't have a field named `id`.

### Links between entries

An entry names other entries by slug: its terms (`tag: [php, cms]`),
its authors (`authors: jane`), the `parent` of an entry in a
[nesting collection](content-types.md#nesting-and-order) (such as a category),
and any reference field. A page of a tree is named by its path
(`about/team`). You can write an entry's id instead of its slug, and
Blush reads it as that entry:

```yaml
tag: [php, 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74]
```

Beside the slugs, `refs` can keep each one's id, by the relation's name
(see [Terms and relationships](content-types.md#terms-and-relationships)),
then by the slug written. The name is usually the key too; when a
relation is written under another key, such as `authors` for a relation
named `author`, its ids are still under `refs.author`:

```yaml
tag: [php, cms]
refs:
  tag:
    php: 0199a1b4-2c6d-7e3f-8a90-1b2c3d4e5f60
    cms: 0199a1c8-4d5e-7f60-9a1b-2c3d4e5f6071
```

When a slug in the list and its id in `refs` disagree (the term was
renamed since), the id wins, so the entry keeps its link. A
`parent` is kept in `refs.parent` too. A page's parent is its folder,
and an id kept for it that names another page is ignored. Types can't
have a field named `refs`.

You don't need `refs` in files you write by hand: Blush writes it
whenever it saves a file, in the admin or from the command line. It
also writes each value as the slug its entry has now, so an id you
wrote becomes a slug; a value that names nothing yet is kept as you
wrote it. When you rename an entry or move a page in the admin, the
entries linking to it are rewritten to its new slug or path. To file
`refs` in files written some other way, run:

```bash
bin/blush content:refs          # list files whose links aren't filed with their ids
bin/blush content:refs --write  # file them
```

or use **Links Between Entries** in Site Health.

`content:lint` reports an entry that names itself, a value that names
entries of two types (write the id to say which), an id of the wrong
type, and a reference field naming an entry that doesn't exist.

## Only Markdown

Every entry is a `.md` file. Other files in `user/content/` aren't
entries and don't show on the site. If you have entries from before this
change in other formats, `content:lint` (and Site Health in the
admin) lists them:

- **`.markdown`:** rename it to `.md`.
- **`.html`:** rename it to `.md`. HTML in a Markdown body still
  renders, as long as raw HTML is allowed (on the [Writing settings
  screen](admin.md#settings)).
- **`.json` or `.yaml`:** move its keys into the front matter of a `.md`
  file, with its `body` (if it has one) below.

## Checking your content

```sh
bin/blush content:lint           # report problems in front matter
bin/blush content:lint --strict  # also unknown keys and old 1.x names
bin/blush content:ids --write    # give files without an id one
bin/blush content:list           # everything Blush has found
bin/blush content:new page "Contact me"   # create a new entry
```

`content:lint` is worth running before you publish: it catches bad dates
(including ones that aren't on the calendar, such as a placeholder
`2019-00-00`, which would quietly be read as 2018-11-30), misspelled values, two files claiming the same URL, a page a content
type's URLs hide (such as `blog/2026.md` when the blog has yearly
archives at `/blog/2026`), term parents that are missing or loop, and
ids that are missing, not valid, or shared.
