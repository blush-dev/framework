# Credits and profiles

Profiles are the people your entries credit: a post's authors, a
recipe's cooks. Each person is one profile, with a page of their own, and
types credit them through credit relations.

## Profiles

The built-in `profile` type holds people, in `user/content/_profile/`.
Each file is one person: the title is their public name, `subtitle` a
line under it (such as "Food editor"), `avatar` a portrait from
`user/media`, `linkable: false` to lock it against being linked to an
account (see [Accounts](accounts/README.md)), and the body their bio.
Each profile has a page at `/profiles/jane`.

Every profile is a file: a person credited with no file is left out of
bylines and has no page. `content:lint` reports it, and
`bin/blush content:terms --write` (or **Terms and Profiles** in Site
Health) writes the missing files. An [account](accounts/README.md) can
be linked to a profile.

To turn profiles off, see
[Turning off a built-in type](content-types.md#turning-off-a-built-in-type).

## Crediting people

A type credits people through a **credit relation**: a
[relation](relationships.md) of kind `credit`, from the types
that credit, to the profiles type. Each names its
front matter key in the types' own words: a blog credits its authors,
and a recipe box its cooks and photographers. Every credit points at the
same profiles, so Jane is one profile whether she wrote a post or
cooked a recipe.

Nothing credits anyone until a relation says so. The skeleton ships
`user/data/relations/authors.json`, crediting posts' authors:

```json
{
	"kind": "credit",
	"from": ["post"],
	"to": ["profile"],
	"aliases": ["author"],
	"label": "Authors"
}
```

To credit authors on another type, add it to `from`. The admin's
new-type form does that when you ask for authors. More credits are more
relations:

```json
{
	"kind": "credit",
	"from": ["recipe"],
	"to": ["profile"],
	"label": "Cooks",
	"required": true
}
```

In PHP, `Relation::authors(['post', 'recipe'], 'profile')` is the
`authors` relation above, and other credits are a `Relation` of
`RelationKind::Credit`. A credit takes the [relation
options](relationships.md#relation-options), with these differences: `to` is the
profiles type and nothing else, it's always `ordered` (the order names
are written in is the byline's), a translation adds its own names to
its original's (`translations: add`), and its `inverse` has a `page`
and an `archive` by default ([below](#people-archives)). `singular`
names one ("Cook"), made from the label when it's left out.

## Bylines

A type with one credit uses it for the byline. A type with several
names its byline with the type's `byline` option. In
`user/data/types/recipe.json`:

```json
{
	"byline": "cooks"
}
```

The default theme's byline uses that relation ("By Jane") and labels
the rest ("Photographer: Sam").

## People archives

A credit's `inverse.archive` is a word (its name, by default), so every
type it credits gets two kinds of page under its own prefix, as [any
relation with an archive word](relationship-archives.md#archives-for-references) does:

- `/recipes/cooks` lists the people at least one published recipe
  credits as a cook, by name, each with their bio's start.
- `/recipes/cooks/jane` is Jane's archive there: her bio, then the
  recipes crediting her as a cook, listed and paged as the type lists
  them, with feeds at `/recipes/cooks/jane/feed` (and `/feed/atom`,
  `/feed/json`) when the type has a feed. Someone no recipe credits as a
  cook has no archive there.

`"inverse": {"archive": false}` turns them off. Bylines link to the
archive under the entry's own type, or to the profile's page when the
credit has no archives. The sitemap includes the archives.

To give the list a title and an introduction, add a page named after
the word to the type's folder: `user/content/_recipe/_cooks.md`. Its
title replaces "Cooks" and its body introduces the list.

To write something for one person's archive instead of their bio, add
`user/content/_recipe/_cooks/jane.md`. While it's published, its title
and body introduce Jane's cook archive; the profile's bio is used
otherwise.

The leading underscore keeps both kinds of page out of the type's
listings and feeds, so they have no address of their own. Every
relation with an archive word takes the same two pages.

## Profile pages

Each profile has a page of its own at `/profiles/jane`: the profile,
then every published entry of any type crediting them, newest first
unless the profiles type's `listing` says otherwise. It's a credit's
`inverse.page`, on by default. A profile has a page before anything
credits them.

For the profiles type, `urls` sets where profiles' pages are (its
`prefix`, `profiles` by default), `listing` how a profile's page lists
the entries crediting them, and `feed` whether each profile has a feed.
It doesn't take `dateArchives`, and nothing answers at
`/profiles` itself.

They're at `/profiles/{name}`, with their files in `_profile/`. To move
their pages, redefine the `profile` type. For example, a 1.x site with
its author pages at `/authors/jane`, `user/data/types/profile.json`:

```json
{
	"kind": "profiles",
	"urls": { "prefix": "authors" }
}
```

Its author files move from `authors/` to `_profile/`.
