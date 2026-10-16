# Translations

You can write your site in more than one language: each translation is a
file beside its original, served under its language's code.

## Listing your languages

List the other languages in `config/app.php`. Your site's `locale` is the
default language; the others are named by a short code:

```php
return new AppConfig(
	locale: 'en_US',
	languages: [
		'fr'    => ['locale' => 'fr_FR', 'label' => 'Français'],
		'pt-br' => 'pt_BR'
	]
);
```

Codes are lowercase, with hyphens (`fr`, `pt-br`). To make a listed
language the default, set the site's locale (Settings → General →
Language and region) to its locale (`fr_FR`) or its code (`fr`), or to
the code with another region (`fr_CA`, used as the site's locale).

## Translating a file

Translate a file by copying it beside the original with the code before
the extension:

| Original | French translation | French URL |
|---|---|---|
| `about.md` | `about.fr.md` | `/fr/about` |
| `about/index.md` | `about/index.fr.md` | `/fr/about` |
| `_post/2026-10-04.hello.md` | `_post/2026-10-04.hello.fr.md` | The post's URL, under `/fr` |
| `topics/music.md` | `topics/music.fr.md` | `/fr/topics/music` |

In the admin, an entry's **Language** field does the renaming for you
(see [the admin](../admin/README.md)).

A translation can be a plain file or a folder, whatever its original
is: `about/index.md` and `about.fr.md` are translations of each other.

A translation can also have a name of its own, when it gives the id of
the entry it translates in `translation_of`. It still needs its
language's code before the extension:

```yaml
---
# _post/printemps.fr.md, a translation of _post/2026-10-04.hello.md
title: Bonjour
translation_of: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74
id: 0199b6e3-1a2b-7c41-8d2e-6b9f0c3b1e75
---
```

`translation_of` names the original (the file without a language code),
of the same type. `content:lint` reports one that names no entry, a
translation, another type's entry, or that's on a file without a
language code.

## Translated URLs

The default language keeps its URLs; every other language's are under
its code. Give a translation its own `slug` to translate its URL too:
`about.fr.md` with `slug: a-propos` is at `/fr/a-propos`. Pages inside a
translated page's folder use its translated slug: with that,
`about/biography.fr.md` (`slug: biographie`) is at
`/fr/a-propos/biographie`. A folder whose page isn't translated keeps
its name.

## Pages that aren't translated

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

## Lists and pages in each language

With `'include'`, `/fr`, the French collections, topics, and date
archives, and the lists blocks and templates make on French pages
include the originals. Otherwise each language lists only its own
entries. A French page is shown in French: its dates, `<html
lang="fr-FR">`, and the theme's text when the theme is translated. A
page in a language written right to left, such as Arabic or Hebrew,
has `dir="rtl"`, so it reads right to left.
Date archive titles are written the language's way too
(`3 de diciembre de 2025`). Blocks follow the page as well, in
templates and in Markdown: a
`::acme/recent-posts` in `about.fr.md` lists the French posts, and
`time`, `progress`, `meter`, and `file` write their dates and numbers
the French way (`1 250,5`, `62 %`).

Each page tells search engines about its versions in other languages
with `hreflang` links in its `<head>`: a page's published translations,
and for the homepage, collections, topics, and date archives, the same
page in each language that has entries for it. The default language's
version is also the `x-default`. A page in only one language has none.

## Translated terms

When you translate a topic or tag, keep the original's name in your
entries' front matter (`category: music`, not `musique`); the French
entries still find the French topic. The same goes for a topic's
`parent`: write the original's name, and the French topic's parent is
its French translation.

A relation's `translations` option says which links a translation has:
its own, its original's, or both (see
[Relation options](relationships.md#relation-options)).

## Not yet translated

A few things don't work in other languages yet:

- Feeds, sitemaps, `llms.txt`, profiles, and author archives are in the
  default language only.
- The admin shows and edits only the default language's entries; edit
  translations as files.

`bin/blush content:lint` warns about a file named with the default
language's code beside the original (`about.en.md` next to `about.md`).
