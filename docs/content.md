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

- **In a collection or taxonomy, everything before the last `.` is
  ignored.** Use it to sort files: `01.intro.md`, `02.setup.md`, or
  `2026-09-26.hello.md` become `intro`, `setup`, and `hello`. Listings
  are sorted by file name unless you say otherwise. Pages and profiles
  don't take these order prefixes: name them as their URLs read
  (`about.md`). A prefixed page still works, but `content:lint` reports
  it as an error, because a prefix orders nothing among pages and its
  folder wouldn't nest under it. Hidden files (`_`-prefixed, or in a
  `_` folder) are left alone, since they have no address.
- **`index.md` is its folder's page.** `about/index.md` is the page at
  `/about`.
- **Set `slug:`** in front matter to choose the URL name yourself.
- **Folders nest pages.** `about/team.md` is a subpage of the `about`
  page, which themes can use for breadcrumbs and lists of subpages.

Folders can also hold a whole [content type](content-types.md), like a
blog whose posts are listed at `/blog`. A type's folder starts with an
underscore (`_blog`) so it stands apart from your page folders. Plain
folders of pages need no setup.

## Front matter

Front matter is YAML between two `---` lines at the top of the file. Only
`title` is needed in practice. The built-in keys:

| Key | What it does |
|---|---|
| `title` | The entry's title |
| `subtitle` | A line shown under the title |
| `slug` | The URL name, instead of the file name |
| `published` | The publish date, such as `2026-09-26 09:00:00 -05:00`. A date in the future schedules the entry. (`date` works too.) |
| `updated` | When it last changed; defaults to `published`, then the file's modified time |
| `status` | `published` (the default) or `draft` |
| `visibility` | `public` (the default), `unlisted`, or `hidden` |
| `summary` | A short Markdown summary for listings and feeds. Without one, the first 50 words are used. (`excerpt` works too.) |
| `image` | A featured image |
| `authors` | One author or a list, by profile slug (`author` works too), in types that credit authors. A type can credit people under other names too, such as `cooks` (see [Crediting people](content-types.md#crediting-people)) |
| `position` | A page's or term's place among its siblings, a whole number, lowest first. Those without one follow, by title. Only pages (and other [trees](content-types.md#trees)) and taxonomy terms have it |
| `redirect_from` | Old URLs that should redirect here (see below) |
| `template` | The theme template to use, such as `single-wide` (`view` works too) |
| `layout` | The theme layout to use |
| `class` | Extra CSS classes for the page's `<body>` |
| `stylesheet` | An extra stylesheet for this page |
| `collection` | List other entries on this page (see [Content types](content-types.md#listing-entries)) |

Any other key you add is kept and available to your theme. [Taxonomies](content-types.md#taxonomies) add
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

The default language keeps its URLs; every other language's are under
its code. Give a translation its own `slug` to translate its URL too:
`about.fr.md` with `slug: a-propos` is at `/fr/a-propos`. Pages inside a
translated page's folder use its translated slug: with that,
`about/biography.fr.md` (`slug: biographie`) is at
`/fr/a-propos/biographie`. A folder whose page isn't translated keeps
its name.

Each language lists only its own entries: `/fr` and the French
collections, topics, and date archives show only the French
translations. A French page is shown in French: its dates, `<html
lang="fr-FR">`, and the theme's text when the theme is translated.
Date archive titles are written the language's way too
(`3 de diciembre de 2025`). Components follow the page as well, in
templates and in Markdown: a
`::app/recent-posts` in `about.fr.md` lists the French posts, and
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

- A page that isn't translated has no French address: `/fr/about` is
  "not found" until `about.fr.md` exists.
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

  To caption something else, such as a table or a code block, wrap it in
  a [figure](components.md#built-in-components): `:::figure[Caption]` …
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
- **Spans:** text in brackets followed straight away by attributes,
  `[text]{.class #id}`, becomes a `<span>` with them, for a class or id
  on a few words. A link (`[text](/url){.class}`) or a word with a link
  reference definition stays a link, with the attributes on it.
- Links that start with `/` become full URLs, so they still work in feeds.
- Raw HTML is allowed.

You can add more CommonMark extensions, such as heading permalinks, in
`config/markdown.php`; see
[Configuration](configuration.md#markdown).

### Components

Components add richer blocks to your writing:

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::

::embed[Our launch video]{url="https://youtu.be/..." title="Launch video"}
```

`callout`, `gallery`, `figure`, and `embed` work in every theme, and themes
can add their own. See [Components](components.md) for the syntax, every
built-in component, and making your own.

## Other formats

Besides Markdown (`.md`), an entry can be:

- **HTML** (`.html`), with the same front matter.
- **JSON or YAML** (`.json`, `.yaml`), for data-only entries. The keys are
  the front matter, and an optional `body` key holds Markdown.

## Checking your content

```sh
bin/blush content:lint           # report problems in front matter
bin/blush content:lint --strict  # also unknown keys and old 1.x names
bin/blush content:list           # everything Blush has found
bin/blush content:new page "Contact me"   # create a new entry
```

`content:lint` is worth running before you publish: it catches bad dates
(including ones that aren't on the calendar, such as a placeholder
`2019-00-00`, which would quietly be read as 2018-11-30), misspelled values, two files claiming the same URL, a page a content
type's URLs hide (such as `blog/2026.md` when the blog has yearly
archives at `/blog/2026`), and term parents that are missing or loop.
