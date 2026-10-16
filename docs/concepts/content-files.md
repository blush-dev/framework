# Content files

Everything visitors read lives in `user/content/`. Each file is an
**entry**: a page, a post, a tag, and so on. You write entries in Markdown
with a small block of settings at the top, called
[front matter](front-matter.md). This page explains how a file's name and
folder become its URL.

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
  own file name pattern gives a file; folders never take one. Files
  named with a `_` (a relation archive's `_authors.md`) are left alone,
  since they have no address of their own. Any type can name the files it creates for you by a
  pattern ([Naming new files](file-names-and-urls.md#naming-new-files)).
- **A collection's entries are files in its folder**, never folders of
  their own ([Collections are flat](file-names-and-urls.md#collections-are-flat)),
  unless the collection keeps them in folders by year or letter
  ([Folders for many files](file-names-and-urls.md#folders-for-many-files)).
  Those folders are never part of an address.
- **`index.md` is its folder's page.** `about/index.md` is the page at
  `/about`. (Not in a collection, which is flat.)
- **Set `slug:`** in front matter to choose the URL name yourself.
- **Folders nest pages.** `about/team.md` is a subpage of the `about`
  page, which themes can use for breadcrumbs and lists of subpages. The
  folder needs a page of its own (`about.md` or `about/index.md`): a
  page in a folder with no page is at the top, at `/team`. Run
  `bin/blush content:parents --write` (or **Parent Pages** in Site
  Health) to write each missing one as a draft, titled by its folder,
  and the pages under it are back at their addresses. Creating a page
  in the admin or with `content:new` writes any parent it needs.

Folders can also hold a whole [content type](content-types.md), like a
blog whose posts are listed at `/blog`. A type's folder starts with an
underscore (`_blog`) so it stands apart from your page folders. Plain
folders of pages need no setup.

## Names Blush keeps

A file or folder name never hides an entry or makes it a draft: its
front matter does (see [Status and visibility](status-and-visibility.md)).
The only `_` names Blush keeps are its own: each type's folder (`_post`),
a relation archive's pages (`_post/_authors.md`), and the
[error pages](error-pages.md) (`_errors/404.md`).

Every entry is a `.md` file; see
[Only Markdown](markdown.md#only-markdown) for other formats.
