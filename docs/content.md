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
| `user/content/index.md` | `/` (the home page) |
| `user/content/about.md` | `/about` |
| `user/content/about/index.md` | `/about` (same page, as a folder) |
| `user/content/about/team.md` | `/about/team` |

A few rules make the file names flexible:

- **Everything before the last `.` is ignored.** Use it to sort files:
  `01.about.md`, `02.contact.md`, or `2026-09-26.hello.md` all become
  `about`, `contact`, and `hello`. Listings are sorted by file name unless
  you say otherwise.
- **`index.md` is its folder's page.** `about/index.md` is the page at
  `/about`.
- **A folder with an `index.md` is a "bundle".** Put the page's images next
  to it (`about/index.md`, `about/me.jpg`) and link them by name. See
  [Media](media.md).
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
| `authors` | One author or a list, by slug (`author` works too) |
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

## Error pages

Create `user/content/_errors/404.md` to write your own "not found" page:

```markdown
---
title: "Page not found"
---

Sorry, there's nothing here. Try the [home page](/).
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
- Links that start with `/` become full URLs, so they still work in feeds.
- Raw HTML is allowed.

You can add more CommonMark extensions, such as attributes
(`{.alignwide}`) or heading permalinks, in `config/markdown.php`; see
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

`content:lint` is worth running before you publish: it catches bad dates,
misspelled values, two files claiming the same URL, a page a content
type's URLs hide (such as `blog/2026.md` when the blog has yearly
archives at `/blog/2026`), and term parents that are missing or loop.
