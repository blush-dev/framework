# Front matter

Front matter is the block of settings at the top of an entry's file: its
title, date, status, and the rest.

It's YAML between two `---` lines at the top of the file.
Every entry needs an `id` (see [Ids](ids-and-links.md)); otherwise only
`title` is needed in practice.

## Built-in keys

| Key | What it does |
|---|---|
| `title` | The entry's title |
| `subtitle` | A line shown under the title |
| `slug` | The URL name, instead of the file name |
| `date` | The entry's date, such as `2026-09-26 09:00:00 -05:00`. A date in the future schedules the entry. |
| `expires` | When the entry comes off the site, in a type with [expiry](status-and-visibility.md#expiry). Once it passes, the entry is expired: off the site, as a draft is, until you move or remove the date. It has to come after `date` |
| `updated` | When it last changed; defaults to `date`, then the file's modified time. Every save and bulk change in the admin sets it |
| `status` | `published` (the default), `draft`, or `trash`, or a status a plugin adds (see [Statuses](../features/statuses.md)). The admin's **Move to trash** sets `trash`, which takes the entry off your site. A status the site doesn't have keeps the entry off the site, and `content:lint` warns about it |
| `trashed` | When the entry was moved to the trash; set with `status: trash`, and removed when it's restored |
| `visibility` | `public` (the default), `unlisted`, or `hidden` (see [Who sees an entry](status-and-visibility.md#who-sees-an-entry)). Only in types that have it |
| `summary` | A short Markdown summary for listings and feeds. Without one, the first 50 words of the content's paragraphs and lists are used. (`excerpt` works too.) |
| `image` | A featured image: an image [media file's](media/README.md) id, which the admin writes when you choose one |
| `authors` | One author or a list, by profile slug (`author` works too), in types the `authors` relation credits. A type can credit people under other names too, such as `cooks` (see [Credits and profiles](credits-and-profiles.md)) |
| `position` | A page's or term's place among its siblings, a whole number, lowest first. Those without one follow, by title. Only pages (and other [trees](trees.md)) and collections that [nest or are ordered by position](relationships.md#nesting-and-order), such as categories and tags, have it |
| `translation_of` | For a translation, the id of the entry it translates, which links them whatever their file names (see [Translations](translations.md)) |
| `template` | The theme template to use, one name, such as `template-wide` (`view` works too). The admin's **Template** row offers the ones the theme [declares](../themes/README.md) |
| `collection` | List other entries on this page (see [Listing entries on any page](archives-and-feeds.md#listing-entries-on-any-page)) |
| `id` | The entry's id, a UUID that never changes (see [Ids](ids-and-links.md)). Blush writes it, last |
| `refs` | The ids of the entries this one names, such as its terms and authors (see [Links between entries](ids-and-links.md#links-between-entries)) |

Any other key you add is kept and available to your theme.
[Relations](relationships.md) add their own keys too, such as
`tag: [php, cms]`. A type can declare its own keys as
[custom fields](custom-fields.md).

## An example

```markdown
---
title: "Launch day"
subtitle: "Two years in the making"
date: 2026-10-01 09:00:00 -05:00
authors: jane
tag: [news, releases]
summary: "We're live. Here's what's new."
image: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74
---

Today's the day...
```
