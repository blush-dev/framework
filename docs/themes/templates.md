# Templates

Templates are the PHP files in a theme's `views/` folder that draw its
pages. They're plain PHP, unless a plugin adds another template language
(see [Template engines](../features/template-engines.md)).

## A template

Here's a simplified `single.php`:

```php
<?php

declare(strict_types=1);

$template->layout('base');

?>
<article class="entry">
	<h1><?= e($entry->title) ?></h1>

	<?php if ($theme->setting('showDate')) : ?>
		<p><?= e($template->date($entry->date)) ?></p>
	<?php endif ?>

	<?= raw($entry->content()) ?>
</article>
```

`$template` is the template itself: it's how a template reaches the calls
below. Its data (such as `$entry`, `$page`, `$site`, and `$messages`)
are plain variables. For editor autocomplete, list the ones a file uses
at the top of it:

```php
/**
 * @var Blush\View\Template        $template
 * @var Blush\Translation\Messages $messages
 * @var Blush\Content\Entry\Entry  $entry
 */
```

## Escaping

**Always escape output.** Use `e()` for text, `attr()` for attributes,
`url()` for links, and `raw()` only for HTML you trust, such as a rendered
entry body. `raw()` marks the HTML as trusted, and the mark travels with
it: `e()` leaves trusted HTML (and a component) as it is, while `attr()`
escapes everything, since an attribute never holds markup. Never `raw()`
text a visitor or a writer typed, such as a title.

## What a template can use

| Call | What it does |
|---|---|
| `$template->layout('base')` | Wrap this template in `layouts/base.php`; a name with a folder is in `layouts/` too (`layout('shells/wide')` is `layouts/shells/wide.php`) |
| `$template->section('content')` | In a layout, print the wrapped template |
| `$template->start('name')` … `$template->stop()` | Capture a named section |
| `$template->include('partials/header', key: $value)` | Include another template |
| `$template->include(['partials/card-post', 'partials/card'])` | Include the first of these that exists |
| `$template->includeIf('partials/sidebar')` | Include it only if it exists |
| `$template->includeWhen($condition, 'partials/x')` | Include it only when the condition is true |
| `$template->includeUnless($condition, 'partials/x')` | Include it unless the condition is true |
| `$template->each('partials/card', $entries, as: 'entry', empty: 'partials/none')` | Include a template once per item (see below) |
| `$template->component('notebook/card', title: '...')` | Render a component (see [Components](../features/components/README.md)) |
| `$template->componentIf('acme/powered-by')` | Render a component only if it exists, such as one from a plugin the theme suggests |
| `$template->hasComponent('acme/powered-by')` | Whether a component exists |
| `$template->block('callout', variant: 'tip')` | Render a block, as content would (see [Blocks](../features/blocks/README.md)) |
| `$template->blockIf('acme/share')` | Render a block only if it's registered |
| `$template->hasBlock('acme/share')` | Whether a block is registered |
| `$template->route('home')` | A named route's URL (`bin/blush routes:list`), with any parameters: `route('post.collection.month', ['year' => 2026, 'month' => '05'])`. On a [translated](../concepts/translations.md) page, it's that language's version when there is one (`/es` for `home`) |
| `$template->date($entry->date)` | A date in the site's date format and the page's language; or give an [ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax), such as `'MMMM y'` |
| `$template->time($entry->date)` | A time in the site's time format |
| `$template->datetime($entry->date)` | Both, joined as the site's language joins them (`October 4, 2026 at 2:30 PM`) |
| `$template->widont($title)` | Escaped text whose last two words won't split across lines, so a title never ends with one word alone |
| `$template->cache('key', fn () => ...)` | Keep a piece of HTML that's slow to build (see [Caching slow parts](caching.md)) |
| `$template->head()` | Add to the `<head>`: title, meta tags, styles, scripts (see [The head and the foot](head-and-foot.md)) |
| `$template->foot()` | Add scripts to the end of the page, and print them just before `</body>` in the base layout |
| `$template->bodyClass()` | The `<body>` classes |

## Dates and times

Dates and times use the formats the site owner picks on **Settings →
General** (`dateFormat` and `timeFormat` in `config/app.php`), so use
them without a format wherever a date is just a date. Pass one only
where your design needs it: a style (`full`, `long`, `medium`, `short`)
or an [ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax),
such as `$template->date($date, 'MMMM y')` for an archive's heading, or
`$template->datetime($date, 'short', 'HH:mm')`. A style can also be
given as `Blush\Clock\DateStyle` (`Full`, `Long`, `Medium`, `Short`), so
your editor completes it and a typo is an error rather than a pattern:

```php
<?php use Blush\Clock\DateStyle; ?>
<?= e($template->date($entry->date, DateStyle::Short)) ?>
```

ICU patterns aren't PHP's
`date()` letters: `MMMM d, y` is PHP's `F j, Y`, and `h:mm a` is `g:i A`.
For a machine-readable date, such as a `<time datetime="">`, use
`$date->format(DATE_ATOM)`.

## What every template gets

Every template gets four objects besides `$template`:

| Variable | What it is |
|---|---|
| `$site` | The site: `name`, `url`, `description`, `locale`, `lang`, and `dir`, and `$site->setting('name')` for a setting of the site's own ([Site settings in templates](site-settings.md)) |
| `$theme` | Your theme and its parents: `$theme->setting('name')` for a theme setting, `$theme->asset('app.js')` for a file's URL (versioned), `$theme->inline('svg/logo.svg')` for a file's contents (such as an SVG to print with `raw()`), each found in a child theme first; and its `name` |
| `$media` | The media library: `$media->find($value)` (see [Media in templates](media.md)) |
| `$messages` | Your text: `$messages->t('key', 'Text', ...)`, with its text for when no catalog has it ([Translations](translations.md)) |

Content pages also
get `$page`, `$entry` (the entry), `$entries` (a listing, when there is
one), `$type`, and `$title`, and so do their layouts, partials,
blocks, and components, so you don't have to pass them along. Anything you pass to
`include()` wins, so `$template->include('partials/summary', entry: $item)` shows
that entry instead. What an entry offers is on [Entries in templates](entries.md).

## Including once per item

`each()` saves writing a loop around `include()`. Each item reaches the
partial as the variable you name with `as` (`$item` if you don't), along
with `$index` (its position, from `0`) and anything else you pass. With no
items, it includes the `empty` template, if you gave one:

```php
<?= $template->each('partials/entry-summary', $entries, as: 'entry', empty: 'partials/no-entries') ?>
```
