# Directives

A directive is something your content says beyond plain Markdown: a
callout, a gallery, a video, a button. You write it as a line of
Markdown, `:::callout[Heads up]{variant=warning}`, and the active theme
decides how it looks. In the admin's editor, directives are called
**blocks**.

Directives are part of your content, and content outlives themes, so a
directive comes from Blush, a plugin, or your site, never from a theme.
Themes give directives their look; switching themes changes how a
callout looks, never whether it's there. For the reusable pieces a
theme builds its own templates from, see [Components](components.md).

## Directive names

A directive's name has two parts, `{namespace}/{name}`, so directives
from different places never clash:

| Namespace              | Whose directives  | Example         |
|------------------------|-------------------|-----------------|
| `blush`                | The built-in ones | `blush/callout` |
| `app`                  | Your site's own   | `app/post-list` |
| The plugin's namespace | A plugin's own    | `tabs/tabs`     |

A [plugin](extending.md#plugins) declares its namespace in its manifest
(`"namespace": "tabs"`), and no two installed extensions can share one.

**Only the built-in directives have short names:** `callout` is
`blush/callout`. Everything else is always written with its namespace.

## Using directives in content

Three forms, depending on what the directive wraps:

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::

::embed[Our launch]{url="https://youtu.be/…" title="Launch video"}

Read the :app/badge[new]{tone=tip} release notes.
```

| Form              | Use it for                                                   |
|-------------------|--------------------------------------------------------------|
| `:::name` … `:::` | A block that wraps other content (paragraphs, images, lists) |
| `::name`          | A directive on a line of its own, wrapping nothing           |
| `:name[text]`     | A directive inside a sentence                                |

Each directive has one form, the one it's registered with. Of the
built-in ones, those that wrap content (callout, gallery, figure, and
the [layouts](#layout)) are `:::`; audio, video, file, embed, menu,
meter, progress, and the table of contents are `::`; and those that go
in a sentence (abbr, badge, button, cite, dfn, icon, ins, kbd, samp,
small, time, and var) are `:`. Written another way, such as
`:::audio{src=…}` or `::button[…]`, a directive shows only its label,
if it has one, and the admin's editor marks it. A `:::` line for one
that isn't a container never opens a block, so a `:::` after it still
closes the block around it.

Each part after the name is optional:

- **`[text]` is the label.** It's plain text, such as a title or caption.
  Every directive gets it as the `label` prop, and a `::` or `:`
  directive also gets it as its content.
- **`{…}` holds settings**, which directives call props:
  `key=value`, `key="a value with spaces"`, or `key='…'`. A bare `key` means
  `true`, `.name` adds a CSS class, and `#name` sets the `id`.

A name no one registered, or a short name that isn't built in, shows as
plain text, so a typo never breaks a page. To see every directive you
can use, run:

```sh
bin/blush directive:list
```

### Built-in directives

These work in every theme, because Blush draws them itself:

| Directive | Example                                                | Props                                                                                                                                                                                                                                                                  |
|-----------|--------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `callout` | `:::callout[Title]{variant=info}` … `:::`              | [Variants](#variants): `info`, `tip`, `warning`, or `danger`; without one, it's a plain note. The label is the title.                                                                                                                                                  |
| `gallery` | `:::gallery{columns=3}` … `:::`                        | `columns`: 1 to 6 (default 3). `layout`: `flex` (default; rows that grow to fill the width) or `grid` (even columns). Wrap images in it, one to a line; each is a figure.                                                                                                                               |
| `figure`  | `:::figure[Caption]` … `:::`                           | Sets anything apart with a caption (the label): an image, a table, a code block, a quote. An image on its own line inside it is just the image. For a lone image, you don't need it: an image on its own line is already a figure, with its quoted title as the caption. |
| `embed`   | `::embed[Caption]{url="https://youtu.be/…" title="…"}` | `url`, `title`. YouTube and Vimeo (and [providers you add](configuration.md#embeds)) play in a frame at the video's real shape, named by its own title; YouTube and Vimeo in privacy-friendly mode, from the URL's start time (`?t=90`). Any other URL becomes a link. |

Themes can restyle these, and add [variants](#variants) to them.

### Layout

Four built-in directives arrange other blocks. Their layout works in
every theme; themes style them further through their `directive-group`,
`directive-grid`, `directive-row`, and `directive-stack` classes.

| Directive | Example                           | Props                                                                                                                                                          |
|-----------|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `group`   | `:::group{.highlight}` … `:::`    | Use it to put a class or id on several blocks.                                                                                                                 |
| `grid`    | `:::grid{columns=3}` … `:::`      | `columns`: the most columns, 1 to 12 (default 2). `min`: the narrowest a column gets before the grid drops a column (default `12rem`; `0` never drops). `gap`. |
| `row`     | `:::row{justify=between}` … `:::` | `justify`: `start` (default), `center`, `end`, or `between`. `align`: `center` (default), `start`, `end`, `stretch`, or `baseline`. `wrap=false`. `gap`.       |
| `stack`   | `:::stack{gap=2rem}` … `:::`      | `align`: `stretch` (default), `start`, `center`, or `end`. `gap`. Blocks one above another, with the gap in place of their margins.                            |

All four also take `tag`, the element they render as: `div` (the
default), `section`, or `aside`. A section or aside is a landmark, so
give it a label to name it for screen readers:

```markdown
:::grid[Related reading]{tag=aside columns=3}
…
:::
```

Each block inside a grid is a cell, and each block inside a row or a
stack is an item. To put several blocks in one cell, wrap them in a
`group`. Directives nest with `:::` throughout: each `:::` line closes
the most recent directive that's still open.

```markdown
:::grid{columns=2}
:::group
### Fast
No database, so pages are quick.
:::

:::group
### Simple
Every page is a file you can edit.
:::
:::
```

`min` and `gap` take a CSS length, such as `12rem`, `240px`, or `30%`.
Without `gap`, the theme's `--layout-gap` custom property is used, or a
default.

### Media

Three built-in directives play or offer files. Their files are found the
same way as an image's: `/media/…` is in your media folder (the first
`/` is optional, so `media/song.mp3` works too). Like
Markdown's own links, they become full URLs (`https://example.com/media/song.mp3`),
so they still work in feeds.

| Directive | Example                                                                     | Props                                                                                                                                                                                                             |
|-----------|-----------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `audio`   | `::audio[Episode 12]{src=/media/episode.mp3}`                               | `src`. `preload`: `metadata` (default), `none`, or `auto`. `loop`. The label is the caption. It plays in Blush's player (see below). The `card` variant draws a card (see below), with `title`, `artist`, `album`, and `art`. |
| `video`   | `::video[Launch day]{src=/media/launch.mp4 poster=/media/launch.jpg}`       | `src`, `poster` (an image shown before it plays), `track` (a WebVTT captions file, in the page's language). `width` and `height`, which default to the poster's. `preload`, `loop`, `muted`. The label is the caption. |
| `file`    | `::file[The annual report]{src=/media/report.pdf}`                          | `src`. The label is the link text (the file's name without one). It shows the file's type, and its size when it's in your media folder.                                                                           |

Audio and video play in Blush's own player: a round play button, a bar
to move through it, the time, and the volume (a mute button with a
slider over it), plus captions and full screen for video. The volume is
one setting for every player on the page, and the browser remembers it
for the next visit. On phones that keep the volume to themselves
(iPhones and iPads), only the mute button shows. Its script and styles load only on pages that play
something, and until the script loads, the browser's own controls
show. Its button labels come from the `player` messages, which a theme
can reword ([Translations](themes.md#translations)), and a theme
restyles it with the `--player-*` properties
([Scripts and styles](themes.md#scripts-and-styles)).

For a song or an episode, `variant=card` draws the audio as a card:
its artwork beside its title and who made it, over the player.

```markdown
::audio{src=/media/morning-song.mp3 variant=card}

::audio[Episode 12]{src=/media/episode-12.mp3 variant=card artist="The Show" art=/media/show.jpg}
```

Each part comes from a prop first, then from the file:

- The title is `title`, else the label, else the file's title in the
  [media library](media.md), else the one saved in the file itself.
- Under it, `artist` and `album`, else the ones saved in the file.
- The artwork is `art`, an image, else the file's
  [artwork](media.md#artwork) from the library, else the cover art saved
  in the file, which Blush serves at `/media-artwork/…` beside
  `/media/…`. Without any, the card has no artwork.

A video without a `poster` uses its artwork the same way, and then,
without `width` and `height`, the video's own size.

Only the [file types your site allows](media.md#allowed-file-types) are
served, so check the list before offering other kinds of files for
download.

### Menus

`::menu{name=social}` shows one of your site's [menus](menus.md), by the
theme location that shows it. `label` names the navigation for screen
readers. In content, no link is marked as the current page, since the
same content is shown on every page. Themes usually print menus from
their templates instead:
`<?= $template->directive('menu', name: 'primary') ?>`.

### Table of contents

`::toc` lists the page's headings, nested by level, each linking to its
heading. Put it wherever the list should appear:

```markdown
::toc[On this page]

## Installing

### Requirements

## Setting up
```

The label is shown as its title. It lists headings from `min` to `max`
(levels 2 and 3 by default; `::toc{max=4}` goes deeper). Headings get an
`id` to link to only on pages with a table of contents, and a heading
that already has one (or a heading permalink) keeps it. It's left out
of excerpts and word counts, and shows nothing when a page has no
headings in range.

### Buttons

`:button` is a link that looks like a button, with an optional icon. It
goes inside a sentence, or on a line of its own (a paragraph of its
own):

```markdown
:button[Get started]{url=/start icon=arrow-right iconPosition=end}

:button[Download the guide]{url=guide.pdf icon=download variant=secondary}

:button[Share this post]{url=/share icon=share-2 iconOnly}
```

| Prop           | What it does                                                                              |
|----------------|-------------------------------------------------------------------------------------------|
| `url`          | Where it goes (required)                                                                  |
| `icon`         | Any [icon](#icons), shown before the text                                                 |
| `iconPosition` | `start` (the default) or `end`, to show the icon after the text                           |
| `iconOnly`     | Shows only the icon; the label still names the button for screen readers and as a tooltip |

The label is the button's text, and it's required, even for an
icon-only button. Without a [variant](#variants) it's the main, filled
button; `variant=secondary` is an outlined one, for an action beside the
main one. A `url` starting with `/` becomes a full URL, as
Markdown's links do. To put buttons side by side, write them on one
line:

```markdown
:button[Get started]{url=/start} :button[Read the docs]{url=/docs variant=secondary}
```

Or in a sentence: `Read the :button[docs]{url=/docs}.`

### Progress and meters

Two built-in directives show a number as a bar:

```markdown
::progress[Reading challenge]{value=12 max=50}

::meter[Battery]{value=62 low=20 high=80 optimum=100}
```

| Directive  | Use it for                                                             | Props                                                                                                                                                 |
|------------|------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|
| `progress` | How far along something is: a goal, a series, a task                   | `value`, and `max` (100 by default, so `value` alone is a percentage). Leave out `value` for a bar that shows work in progress without an amount.     |
| `meter`    | A measurement within a range: a score, a rating, how full something is | `value`, `min` (0) and `max` (100), and optionally `low`, `high`, and `optimum`, which mark the range's poor and good parts so browsers can color it. |

The label names the bar for screen readers and is shown beside it, and
the value is shown as text too: "24%" out of 100, or "12 of 50".
Values outside the range are kept at its ends.

### Icons

`:icon` shows an icon inside your text:

```markdown
Subscribe to the :icon[RSS feed]{name=rss} feed, or go :icon[]{name=house} home.
```

Blush comes with about 130 icons from [Lucide](https://lucide.dev)
(arrows, people, files, media, messages, alerts, and more); run
`bin/blush icon:list` to see them all. Use Lucide's name for one:
`house`, `arrow-right`, `circle-alert`.

- **The label is for screen readers.** With one (`:icon[Home]{…}`), the
  icon is announced by it; with empty brackets (`:icon[]{…}`), it's
  decoration and skipped. Give a label whenever the icon means
  something the text around it doesn't say.
- Icons are as tall as the text around them and take its color, so
  they fit in headings, links, and buttons alike.
- An icon Blush doesn't know shows nothing.

In a template, `<?= $template->icon('house') ?>` shows a decorative
icon, and `<?= $template->icon('rss', 'RSS feed') ?>` a labeled one.

Icons are named like directives: the built-in ones are `blush/{name}`
(or just `{name}`), and your own are in your theme's, site's, or an
[icon pack's](extending.md#icon-packs) namespace. To add some, put SVG
files in a folder:

| Where                                   | Name                                     |
|-----------------------------------------|------------------------------------------|
| Your theme's `icons/badge.svg`          | `notebook/badge` (the theme's namespace) |
| Your site's `resources/icons/badge.svg` | `app/badge`                              |
| An icon pack's `github.svg`             | `brands/github` (the pack's namespace)   |

To give another namespace's icon your own look, add a file named after
it in a subfolder named for its namespace: a theme's
`icons/blush/house.svg` or `icons/brands/github.svg`, or your site's
`resources/icons/blush/house.svg`. The site's file wins, then the
active theme's, then its parents', then icon packs' and plugins'. Draw
icons in `currentColor` so they take the text's color.

Labels shown in lists (and, later, the admin) are translatable, under
`icons.{name}.label` in the same catalog as the namespace's
[directive text](#labels-and-translations).

### Inside a sentence

These built-in directives mark up words in running text:

```markdown
A :abbr[CMS]{title="content management system"} saves with :kbd[Ctrl+S].
The launch is :time[next Tuesday]{datetime=2026-10-06}. Comments :badge[Beta]{variant=info}
```

| Directive | Example                                         | Props                                                                                                                                                                                  |
|-----------|-------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `abbr`    | `:abbr[CMS]{title="content management system"}` | `title`: what it stands for.                                                                                                                                                           |
| `badge`   | `:badge[New]{variant=tip}`                      | A short label set off from the text. Variants: `info`, `tip`, `warning`, and `danger`; without one it's neutral.                                                                      |
| `cite`    | `:cite[The Hobbit]`                             | The title of a work: a book, film, article, and so on.                                                                                                                                 |
| `dfn`     | `:dfn[Blush] is a flat-file CMS.`               | The term the sentence defines. `title`: the term, when the text says it differently.                                                                                                  |
| `ins`     | `~~$20~~ :ins[free]{datetime=2026-10-06}`       | Text added later, the pair to `~~deleted~~` text. `datetime`: a date, or a date and time, when it was added. `cite`: a link that explains why.                                        |
| `kbd`     | `:kbd[Ctrl+S]`                                  | Keys joined with `+` are a combination, and each key is marked up on its own.                                                                                                          |
| `samp`    | `:samp[File not found.]`                        | What a program prints.                                                                                                                                                                 |
| `small`   | `:small[Prices include tax.]`                   | A side comment or fine print.                                                                                                                                                          |
| `time`    | `:time[next Tuesday]{datetime=2026-10-06}`      | `datetime`: a year (`2026`), month (`2026-10`), date, date and time (`2026-10-06T14:30`, with an optional time zone such as `Z` or `-05:00`), time (`14:30`), or duration (`PT2H30M`). |
| `var`     | `:var[x]`                                       | A variable in math or code.                                                                                                                                                            |

`time` gives software (search engines, calendars) the exact date while
readers see your words. With an empty label,
`:time[]{datetime=2026-10-06}` shows the date in the page's language
(on a [translation](content.md#translations), the translation's) and
your site's time zone and date format, such as "October 6, 2026". A time uses the
site's time format, and a date with a time uses both; a year or a month
shows just those ("October 2026").

For the rest, Markdown already has a way: `==text==` highlights
(`<mark>`), `~~text~~` strikes out (`<del>`), and `[text]{.class}` wraps
text in a `<span>` with a class or id (see
[Writing content](content.md#markdown)).

### Good to know

- **Directives never come from a theme.** They're Blush's, a plugin's,
  or your site's, so switching themes changes how they look but never
  turns them into plain text. Turning off the plugin that adds one does.
- **They're cached with the page's content.** A directive in Markdown is
  drawn once and kept with the rest of the rendered text, until your content
  changes (a publish) or you switch themes. So a slow one, such as a list
  of every post, is still fast. In development nothing is cached.
- **They can't change the page's `<head>`.** A directive drawn by a
  template can add a stylesheet or meta tag; one used from Markdown
  can't. Load what it needs from the theme instead.
- **You can turn them off** with `directives: false` in
  `config/markdown.php` (see [Configuration](configuration.md#markdown)).
  The syntax then shows as plain text.

## Using directives in templates

A template can draw any directive, as content would:

```php
<?= $template->directive('callout', variant: 'tip')->content('<p>Saved!</p>') ?>
```

Props are named arguments. `->content()` fills the directive's content
(in Markdown, the wrapped block or the label). When the content is text,
such as a button's text, you can pass it as `label` instead, and it's
escaped for you:
`$template->directive('button', url: '/start', label: 'Start & go')`.

For pieces of a theme's own design, such as a card or a post header,
use a [component](components.md) instead.

## Variants

A variant is a named style of a directive: `variant=warning` on a
callout, `variant=secondary` on a button. The directive decides what the
thing is; the variant decides how it looks.

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::
```

- **Every directive has Default,** which is what you get without
  `variant` (or with `variant=default`). It's always there, and no theme
  or plugin can rename or replace it, so content that has never been
  given a variant keeps following the theme.
- **A variant the directive doesn't have renders as Default,** such as
  one from a theme that isn't active. `content:lint` warns about these.
- **Variants belong to whoever declares them.** The built-in directives
  come with a few; a theme, your site, or a plugin can add more to
  any directive. A theme's apply only while it (or a child of it) is
  active. This is how a theme adds looks to content without adding
  directives of its own.

The editor in the admin shows a block's variants in a list at the
top of its options, each with its description.

### Styling a variant

A variant adds a class to the directive's root element:
`directive-{name}--{variant}`, such as `directive-callout--warning`. In
a template, `$directive->variant` is the variant's name (`'default'` for
none), and `$directive->isVariant('warning')` checks it.

When a variant needs more than a class, give it its own template:
`views/directives/callout-bordered.php` draws a callout with
`variant=bordered`, and the directive's own template draws the rest.
(For a plugin's directive, that's `{namespace}-{name}-{variant}.php`.)
Variants are meant mostly for looks, but nothing stops one from doing
more.

### Adding variants

A directive class lists its own in its `VARIANTS` constant:

```php
final class Tabs extends Directive
{
	public const array VARIANTS = ['boxed', 'plain'];
}
```

A theme adds variants to any directive in its `theme.json`, by
directive:

```json
{
	"name": "acme/notebook",
	"label": "Notebook",
	"namespace": "notebook",
	"variants": {
		"callout": ["bordered", "compact"],
		"tabs/tabs": ["underlined"]
	}
}
```

Your site, a plugin, or a theme's provider can add (or remove)
variants from PHP, by listening for `DirectiveVariantsCollecting`. It
fires once per directive, the first time its variants are needed, so the
order providers boot in doesn't matter. The second argument is the
namespace whose catalog has the variant's text:

```php
use Blush\Directive\Events\DirectiveVariantsCollecting;
use Blush\Event\Listener\ListenerRegistry;

$this->container->get(ListenerRegistry::class)->listen(
	DirectiveVariantsCollecting::class,
	function (DirectiveVariantsCollecting $event): void {
		if ($event->is('callout')) {
			$event->add('bordered', 'app');
		}
	}
);
```

A variant's name is lowercase letters, digits, and hyphens, starting with
a letter, and it can't be `default`. To add a class other than
`--{variant}`, give it a modifier: `{"name": "compact", "modifier":
"tight"}` in `theme.json`, or `$event->add('compact', 'app', 'tight')`.

Each variant's label and description are translatable, in the catalog
of whoever added it (see [Labels and translations](#labels-and-translations)).

## Making a directive

A directive is a PHP class for its props and logic, plus a template that
draws it. Every class draws itself by default with its `render()`
method, and a theme draws it its own way with a template. Every
directive has a class. Make one in your site or a plugin; a theme can't
register one.

### The class

The constructor declares the props as public properties, and can also ask
for any of Blush's services:

```php
<?php // src/View/RecentPosts.php

declare(strict_types=1);

namespace App\View;

use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Order;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveView;
use Blush\Directive\DirectiveKind;

final class RecentPosts extends Directive
{
	public const DirectiveContent CONTENT = DirectiveContent::Text;

	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	public function __construct(
		private readonly ContentRepository $content,
		public readonly int $limit = 5,
		public readonly string $label = ''
	) {}

	/**
	 * The title, as HTML: the content, or else the label.
	 */
	public function heading(): string
	{
		return $this->contentOr($this->label);
	}

	/**
	 * @return list<Entry>
	 */
	public function posts(): array
	{
		return $this->content->query()->type('post')
			->orderBy('published', Order::Desc)->limit($this->limit)->get();
	}

	/**
	 * Draws it when the theme has no template for it.
	 */
	public function render(): DirectiveView
	{
		return $this->view(dirname(__DIR__, 2) . '/resources/views/directives/app-recent-posts.php');
	}
}
```

- **Props are typed.** Props from Markdown are always strings, so Blush
  converts them to the types you declare: `{limit=3}` arrives as the
  integer `3`. A prop typed as a PHP backed enum gets the matching case,
  and a value the enum doesn't have falls back to the default.
- **`label`** gets the Markdown label (`::app/recent-posts[Latest]`), if
  the constructor takes it.
- **The `ContentRepository` follows the page's language.** On a
  [translated](content.md#translations) page, such as `/fr/a-propos`,
  `$this->content->query()` finds French entries and `term()` finds French
  topics, so `posts()` above lists the French posts, whether a template
  or a translation's Markdown uses the directive. Ask for another
  language with `->language('en')`, or every language with
  `->anyLanguage()`. When the site's `untranslated` setting is
  `'include'`, French lists add the originals of untranslated entries;
  `->withOriginals()` asks for them whatever the setting, and
  `->withOriginals(false)` never does.
- **Name the content for its role.** A directive's main content is
  `$this->content()`: a `:::` block's HTML, or the escaped label for `::`
  and `:`. When it has a role, such as a caption or a title, add a method
  with that name. `$this->contentOr($this->label)` returns the content,
  or else the label escaped, so it works whether a template passes
  `->content()` or `label:`.
- **Methods do the work**, such as `posts()` above, so the template doesn't
  have to.
- **`render()`** is required: the directive's own markup. See
  [Rendering itself](#rendering-itself).
- **`shouldRender()`** returns `false` to draw nothing.
- **`template()`** returns another view to draw with, instead of
  `directives/{namespace}-{name}`.
- **`CONTENT`** says what the directive wraps: `DirectiveContent::None`
  (the default), `Text` (a label), or `Blocks` (a `:::` block).
- **`KIND`** is required: how it's written, the only way it works.
  `DirectiveKind::Container` (`:::`), `Leaf` (`::`, on a line of its
  own), or `Inline` (`:`, in a sentence), from `Blush\Directive`.
  Registering a directive without one fails, and so does one whose
  `KIND` and `CONTENT` disagree: only a directive that wraps blocks
  (`Blocks`) is a container, and one that does is always a container.
- **`HOLDS`** says what a `:::` block takes, when it's only some things:
  `['image']` for Markdown images, or directives' full names. The
  admin's editor then offers only those inside it (the gallery holds
  images). The site renders only those inside it, leaving out the
  rest: text, other blocks, and directives it doesn't list. The images
  in a paragraph with text are kept, each a figure.
- **`modifiers()`** returns BEM modifiers for the root element, such as
  `['warning']` for `directive-callout--warning`.
- **`rootAttributes()`** returns other attributes for the root element,
  such as `['role' => 'note']`.
- **Inner elements can have attribute methods too**, such as the embed's
  `frameAttributes()` for its `<iframe>`. Build them with `self::html([...])`, which
  escapes each value (URL attributes such as `src` and `href` as URLs,
  leaving out unsafe ones), leaves out `null`, `false`, and `''`, and
  prints `true` as the name alone. Include the element's class
  (`$this->block() . '__wrapper'`), so the template is just
  `<div <?= $directive->wrapperAttributes() ?>>`.
- **`ASSETS`** lists the [scripts and styles](extending.md#scripts-and-styles)
  it needs on the page, by handle: `protected const array ASSETS = ['acme/tabs'];`.
  They load only on pages where it's drawn, in an entry's text too.
  Override `assets()` to decide each time it's drawn.
- **`$this->t('key', name: 'value')`** translates text from the theme's
  catalog, as `$template->t()` does, in the page's language.
- **`#[MediaProp]`** on a string parameter makes it a media reference:
  in Markdown, `src=photo.jpg` is found like an image's (next to the
  entry, or in the media folder) and arrives as its full URL.
  **`#[LinkProp]`** marks a link, such as a button's `url`, which becomes
  a full URL when it starts with `/`.

  ```php
  use Blush\Directive\MediaProp;

  public function __construct(#[MediaProp] public readonly string $src = '') {}
  ```

  Name the kind of file it plays, `#[MediaProp(MediaKind::Video)]`
  (`Blush\Media\MediaKind`: `Image`, `Video`, `Audio`, or `File`), and
  the admin's picker offers only that kind for it. Leave it out for a
  prop that takes any file, such as a download.

Register the class in a service provider's `boot()` method:

```php
use Blush\Directive\DirectiveRegistry;

public function boot(): void
{
	$this->container->get(DirectiveRegistry::class)->register('app/recent-posts', View\RecentPosts::class);
}
```

### Rendering itself

Every directive class has a `render()` method, which draws it when no
theme in use (and not your site) has a template for it. It returns one
of three things:

- **A template file it ships with**, `$this->view($path)`, with any more
  variables as named arguments: `$this->view($path, columns: 2)`. The
  file is drawn like a theme's template, with `$template` and
  `$directive`.
- **Its HTML, as a string.** Nothing escapes it for you, so build it
  with `$this->attributes()`, `self::html([...])`, and
  `Blush\View\Escaper`:

  ```php
  public function render(): string
  {
  	return '<span ' . $this->attributes() . '>' . Escaper::html($this->text) . '</span>';
  }
  ```
- **`null`**, when it has no markup of its own and a theme must give it a
  template. Declare the return type as `null` (or a nullable type), and
  `theme:check` warns when no template is found.

A template in the theme chain or your site's `views/directives/` always
wins, so themes restyle a directive without touching its class. This is
how a plugin's directive works in any theme.

### The template

The template is a file in `views/directives/` named
`{namespace}-{name}.php`: here, `resources/views/directives/app-recent-posts.php`.
A theme that draws it its own way has the same file in its
`views/directives/`.

```php
<?php

/**
 * @var Blush\View\Template   $template
 * @var App\View\RecentPosts  $directive
 */

declare(strict_types=1);

?>
<nav <?= $directive->attributes() ?>>
	<?php if ($directive->heading() !== '') : ?>
		<h2 class="directive-recent-posts__title"><?= raw($directive->heading()) ?></h2>
	<?php endif ?>
	<ul class="directive-recent-posts__list">
		<?php foreach ($directive->posts() as $post) : ?>
			<li><a href="<?= url($template->permalink($post)) ?>"><?= e($post->title) ?></a></li>
		<?php endforeach ?>
	</ul>
</nav>
```

The `@var` lines are for your editor: with them, it can suggest
`$directive->limit` and `$directive->posts()` as you type.

The template gets `$directive` and `$template` (the
[template helpers](themes.md#templates), as in any template).
Everything about the directive is on `$directive`:

| On `$directive`             | What it holds                                                          |
|-----------------------------|------------------------------------------------------------------------|
| Props (`->limit`)           | Plain values, as the constructor typed them                            |
| `->content()`               | The main content, as HTML (`''` when there's none)                     |
| `->variant`                 | The [variant](#variants), or `'default'`                               |
| Methods (`->heading()`)     | Whatever the class adds                                                |
| `->attributes()`            | The root element's attributes (below)                                  |
| `->prop('data-id')`         | Any prop as given, including ones the constructor doesn't take         |

Props are plain text, so print them with `e()` or `attr()`. Content is
HTML, so print `content()` and methods that return content
(`caption()`, `text()`, `heading()`) with `raw()`.

`$directive->attributes()` prints the root element's attributes:

- its classes, named after the directive: `directive-recent-posts`, plus
  any modifiers (`directive-callout--warning`) and the `class` prop
  (`.wide` in Markdown);
- its `id`, from the `id` prop (`#latest` in Markdown);
- and the directive's own, such as a callout's `role="note"`.

Pass more to add them: `$directive->attributes(['data-open' => true])`.
Name the rest of a directive's classes after it, BEM-style, as the
built-in ones do (`directive-callout__title`). That keeps them apart from
the rest of a theme's classes.

To change how a built-in directive looks, add your own template with its
name, `views/directives/callout.php` or `views/directives/blush-callout.php`.
Yours wins. The built-in directives draw themselves with Blush's own
templates, in `resources/directives/` (the class's `render()`), and
your template gets the same `$directive` (their classes are in
`Blush\Directive`): start from Blush's file for the one you're
changing.

A file in `views/directives/` that isn't for a registered directive
(such as a theme's `tabs.php` for a directive no plugin adds) is never
drawn; `directive:list` and `theme:check` point it out. Files in
subfolders of `directives/` aren't directives, so you can keep partials
there. Blush's `menu` and `toc` draw their nested lists inside their own
template, so to change the lists, override the whole directive.

`theme:check` warns about a registered class with no template and no
markup of its own.

### Registering a directive

Every directive is a registered class; a name no one registered is
plain text in your content, and a template on its own isn't a
directive. Its props, content, kind, and variants come from its
constructor, `CONTENT`, `KIND`, and `VARIANTS`, so registering takes
only its name and class.

A provider can also replace a built-in directive with its own class:
`register('callout', MyCallout::class)`. The `blush` namespace is only for
the built-in ones, and every other name needs its namespace. A name in a
theme's namespace is refused: themes can't register directives.

### Labels and translations

A directive's label, description, prop names, and variants are
translatable text, kept in the translation catalog of the plugin that
owns its namespace (or your site's, for `app`), under
`directives.{name}`:

```json
{
	"@@locale": "en",
	"@@domain": "acme/hello",
	"directives": {
		"badge": {
			"label": "Badge",
			"description": "A short word set apart from the text.",
			"props": {
				"tone": {
					"label": "Tone",
					"choices": { "info": "Info", "new": "New", "tip": "Tip" }
				}
			},
			"variants": {
				"outline": {
					"label": "Outline",
					"description": "An outline instead of a fill."
				}
			}
		}
	}
}
```

| Namespace            | Catalog                              |
|----------------------|--------------------------------------|
| `app`                | Your site's `resources/lang/en.json` |
| A plugin's namespace | The plugin's `lang/en.json`          |

Any of them can be reworded by your site's catalogs in `user/lang/`
(see [Your own wording](themes.md#your-own-wording)). A directive that
translates text as it renders (`$this->t()` in its class) reads the
theme's catalogs first, then its own plugin's.

A variant someone else adds to your directive, such as a theme's
`bordered` callout, has its text in their catalog, under the same
`directives.{name}.variants.{variant}` key. `theme:check --strict`
notes the variants in a theme's `theme.json` that have no label.

Without a label, one is made from the name (`recent-posts` becomes
"Recent posts").

## Where directives live

| Where                                     | What it does                                                                    |
|-------------------------------------------|---------------------------------------------------------------------------------|
| Blush                                     | The built-in directives, each drawing itself                                    |
| A plugin (registered by its provider)     | Adds its directives while it's on. See [Extending Blush](extending.md#plugins). |
| Your site's `src/` (registered by yours)  | Adds your own, in `app`. See [Extending Blush](extending.md).                   |
| A theme's `views/directives/`             | Draws directives its own way, while it or a child of it is active               |
| Your site's `resources/views/directives/` | Draws directives your way, with every theme                                     |

Your site's templates come first, then the active theme's, then its
parents', then the default theme's, and last the directive's own
`render()`. `bin/blush theme:why directives/callout` shows which file is
used and what it overrides; `directive:list` says `(its own)` for a
directive that no file overrides.
