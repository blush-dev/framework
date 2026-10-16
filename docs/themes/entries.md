# Entries in templates

On a content page, `$entry` is the entry being shown, and `$entries`
the listing, when there is one. This page lists what an entry offers a
template.

## Fields and text

An entry offers `title`, `slug`, `date`, `updated`, `content()`
(its rendered Markdown; `raw()` is the Markdown as written),
`summary()`, `excerpt()`, `subtitle()`, `wordCount()`, `readingTime()`
(in minutes), and `field('name')` for any declared field in its front
matter (a key nothing declares reads as `null`; a theme registers the
keys its templates read, as a plugin [does](../features/fields.md)).

## What it links to

An entry also leads to the entries and files it's linked to:

| Call | What it does |
|---|---|
| `$entry->url()` | Its URL, or `''` when it has no page |
| `$profile->avatar(48)` | A profile's avatar, to print: its picture, else a square box with its initials (see [Avatars](avatars.md)). Empty for an entry that isn't a profile |
| `$entry->link()` | A link to it: `<a href="…">Its title</a>`, escaped and ready to print. Attributes go by name: `link(class: 'tag', ariaCurrent: 'page')` (a camelCase name is dashed, so `ariaCurrent` is `aria-current`; `true` prints the name alone, and `false` or `null` leaves it out). An entry without a title reads "Untitled", and one without a page prints its title alone |
| `$entry->related('tag')` | The entries it [links to](../concepts/relationships.md) through one of its type's relations, such as its tags or a movie's `actors`, in order, each in its language when it's translated |
| `$entry->referencedBy('actors')` | The entries linking to it through a relation, newest first: by its name, or `'movie.actors'` for one type's |
| `$profile->archiveUrl($entry->type)` | Its [archive](../concepts/relationships.md) under a type: that type's entries crediting it through the byline, or through a relation you name (`$cook->archiveUrl('recipe', 'cooks')` is `/recipes/cooks/jane`). `''` when there's none, so a byline links with `$profile->archiveUrl($entry->type) ?: $profile->url()` |
| `$entry->creators()` | Who made it: the profiles its type's [byline relation](../concepts/credits-and-profiles.md) credits, such as a post's `authors` or a recipe's `cooks`, in order, as `related()` gives them; none when the type has no byline |
| `$entry->classifications()` | What it's filed under: the entries every one of its type's classify relations links (its categories and tags), merged, each once |
| `$entry->credits()` | The profiles it credits through every credit relation (its authors, photographers), the byline first, merged, each once |
| `$entry->references()` | The other entries it refers to through every reference relation (a movie's actors, related posts), merged, each once |
| `$entry->translations()` | It in every language it's written in, by language code, the default first, itself included |
| `$entry->relations()` | Its type's relations by name, or those of one kind: `relations(RelationKind::Credit)` for the people it can credit, its [byline relation](../concepts/credits-and-profiles.md) first, or `RelationKind::Classify` for the kinds of terms it's filed under |
| `$entry->linksTo('tag', $id)` | Whether it links to an entry through a relation, by that entry's id |
| `$entry->parent()` | A page's parent page (from its folder), or the parent of an entry in a [nesting collection](../concepts/relationships.md), such as a category, or `null` |
| `$entry->ancestors()` | Its parents from the top down, for breadcrumbs |
| `$entry->children()` | A page's subpages or a nesting collection's child entries, by `position` and then title |
| `$entry->media('image')` | The media file a media field names, such as its `image`, or `null`; the same as `$media->find()` gives (see [Media in templates](media.md)) |

## Lists of related entries

`related()` and `referencedBy()` give a list to loop over and count,
with a little more:

| Call | What it does |
|---|---|
| `->published()` | Only the ones that are published and have a page of their own |
| `->first()` | The first one, such as a post's author, or `null` |
| `->isEmpty()` | Whether there are none |
| `->all()` | Them as an array |
| `->relation` | The relation, whose `label` and `singular` name it ("Photographers", "Photographer"), or `null` when the entry's type has no relation by that name |
| `->byline` | Whether it's the entry type's byline relation, such as a post's `authors` |

Each is looked up once per request and kept, so call them as often as
you like (`$entry->creators()` in a condition and again in a loop is one
lookup); there's no need to save them in a variable first. An entry's
`excerpt()`, `wordCount()`, and `readingTime()` are kept the same way.

These come back whatever their status: a draft or a hidden entry is
there too, so keep to `published()` for what you show or link:

```php
<?php foreach ($entry->related('tag')->published() as $tag) : ?>
	<?= $tag->link(class: 'tag') ?>
<?php endforeach ?>
```

To list everyone an entry credits, byline first, under each relation's
name:

```php
<?php foreach ($entry->relations(Blush\Content\Relation\RelationKind::Credit) as $relation) : ?>
	<?php $profiles = $entry->related($relation->name)->published() ?>
	<?php if (! $profiles->isEmpty()) : ?>
		<p>
			<?= e($profiles->byline ? 'By' : ($relation->label ?: ucfirst($relation->name)) . ':') ?>
			<?= raw(implode(', ', array_map(static fn ($profile): string => (string) $profile->link(), $profiles->all()))) ?>
		</p>
	<?php endif ?>
<?php endforeach ?>
```

## Excerpts

`excerpt(50, $more)` takes the number of words and HTML to end with when
the content is cut short, such as a "Continue reading" link. Without a
summary, the excerpt is the content's prose: the words of its paragraphs
and list items. Headings, images, audio, video, embeds, captions,
callouts, tables, code, footnotes, buttons, and menus are left out.

```php
<?= raw($entry->excerpt(40, ' <a href="' . url($entry->url()) . '">Continue reading</a>')) ?>
```
