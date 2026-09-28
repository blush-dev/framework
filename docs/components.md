# Components

A component is a reusable piece of a page: a callout, a gallery, a list of
posts. You write it once and use it in two places:

- **In your content**, as a line of Markdown: `::app/post-list{limit=5}`.
- **In a theme's templates**, as `$template->component('app/post-list', limit: 5)`.

It's the same component either way. Any component the active theme can
draw is also available in Markdown, under the same name. There's nothing
extra to register.

## Component names

A component's name has two parts, `{namespace}/{name}`, so components from
different places never clash:

| Namespace             | Whose components                          | Example         |
|-----------------------|-------------------------------------------|-----------------|
| `blush`               | The built-in ones                         | `blush/callout` |
| The theme's slug      | A theme's own                             | `notebook/card` |
| `app`                 | Your site's own                           | `app/post-list` |
| An extension's vendor | An extension's (`acme/hello` uses `acme`) | `acme/tabs`     |

**Only the built-in components have short names:** `callout` is
`blush/callout`. Everything else is always written with its namespace.

## Using components in content

Three forms, depending on what the component wraps:

```markdown
:::callout[Heads up]{tone=warning}
Back up your site before updating.
:::

::figure[A caption]{src="/media/photo.jpg" alt="Describe the photo"}

Read the :app/badge[new]{tone=tip} release notes.
```

| Form              | Use it for                                                   |
|-------------------|--------------------------------------------------------------|
| `:::name` … `:::` | A block that wraps other content (paragraphs, images, lists) |
| `::name`          | A component on a line of its own, wrapping nothing           |
| `:name[text]`     | A component inside a sentence                                |

The number of colons is about where the component sits, not what it is:
`::button[…]` on a line of its own renders the button by itself, while
`:button[…]` goes inside a sentence (on its own line, it's wrapped in a
paragraph). Use `::` for a component that stands alone or sits in a
[row](#layout), and `:` in running text.

Each part after the name is optional:

- **`[text]` is the label.** It's plain text, such as a title or caption.
  Every component gets it as the `label` prop, and a `::` or `:`
  component also gets it as its content.
- **`{…}` holds settings**, which components call props:
  `key=value`, `key="a value with spaces"`, or `key='…'`. A bare `key` means
  `true`, `.name` adds a CSS class, and `#name` sets the `id`.

A name the theme doesn't know, or a short name that isn't built in, shows
as plain text, so a typo never breaks a page. To see every component you
can use, run:

```sh
bin/blush component:list
```

### Built-in components

These work in every theme, because the default theme provides them:

| Component | Example                                                | Props                                                                                                                                                                                                                                                                  |
|-----------|--------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `callout` | `:::callout[Title]{tone=info}` … `:::`                 | `tone`: `note` (default), `info`, `tip`, `warning`, or `danger`. The label is the title.                                                                                                                                                                               |
| `gallery` | `:::gallery{columns=3}` … `:::`                        | `columns`: 1 to 6 (default 3). Wrap images in it.                                                                                                                                                                                                                      |
| `figure`  | `::figure[Caption]{src="/media/a.jpg" alt="…"}`        | `src`, `alt`. The label is the caption.                                                                                                                                                                                                                                |
| `embed`   | `::embed[Caption]{url="https://youtu.be/…" title="…"}` | `url`, `title`. YouTube and Vimeo (and [providers you add](configuration.md#embeds)) play in a frame at the video's real shape, named by its own title; YouTube and Vimeo in privacy-friendly mode, from the URL's start time (`?t=90`). Any other URL becomes a link. |

Themes can restyle these or add their own.

### Layout

Three built-in components arrange other blocks. Their layout works in
every theme; themes style them further through their `component-group`,
`component-grid`, and `component-row` classes.

| Component | Example                           | Props                                                                                                                                                          |
|-----------|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `group`   | `:::group{.highlight}` … `:::`    | `tag`: `div` (default) or `section`. A section's label names it for screen readers. Use it to put a class or id on several blocks.                             |
| `grid`    | `:::grid{columns=3}` … `:::`      | `columns`: the most columns, 1 to 12 (default 2). `min`: the narrowest a column gets before the grid drops a column (default `12rem`; `0` never drops). `gap`. |
| `row`     | `:::row{justify=between}` … `:::` | `justify`: `start` (default), `center`, `end`, or `between`. `align`: `center` (default), `start`, `end`, `stretch`, or `baseline`. `wrap=false`. `gap`.       |

Each block inside a grid is a cell, and each block inside a row is an
item. To put several blocks in one cell, wrap them in a `group`, and give
the outer component more colons so the inner ones fit inside it:

```markdown
::::grid{columns=2}
:::group
### Fast
No database, so pages are quick.
:::

:::group
### Simple
Every page is a file you can edit.
:::
::::
```

`min` and `gap` take a CSS length, such as `12rem`, `240px`, or `30%`.
Without `gap`, the theme's `--layout-gap` custom property is used, or a
default.

### Media

Three built-in components play or offer files. Their files are found the
same way as an image's: a name like `clip.mp4` is a file next to the
entry (in a [page bundle](media.md#next-to-the-entry-bundles)), and `/media/…` is in your media
folder (the first `/` is optional, so `media/song.mp3` works too). Like
Markdown's own links, they become full URLs (`https://example.com/media/song.mp3`),
so they still work in feeds.

| Component | Example                                                                     | Props                                                                                                                                                                                                             |
|-----------|-----------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `audio`   | `::audio[Episode 12]{src=episode.mp3}`                                      | `src`. `preload`: `metadata` (default), `none`, or `auto`. `loop`. The label is the caption.                                                                                                                      |
| `video`   | `::video[Launch day]{src=launch.mp4 poster=launch.jpg captions=launch.vtt}` | `src`, `poster` (an image shown before it plays), `captions` (a WebVTT file, in your site's language). `width` and `height`, which default to the poster's. `preload`, `loop`, `muted`. The label is the caption. |
| `file`    | `::file[The annual report]{src=report.pdf}`                                 | `src`. The label is the link text (the file's name without one). It shows the file's type, and its size when it's in your media folder or bundle.                                                                 |

Only the [file types your site allows](media.md#allowed-file-types) are
served, so check the list before offering other kinds of files for
download.

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

`::button` is a link that looks like a button, with an optional icon:

```markdown
::button[Get started]{url=/start icon=arrow-right iconPosition=end}

::button[Download the guide]{url=guide.pdf icon=download variant=secondary}

::button[Share this post]{url=/share icon=share-2 iconOnly}
```

| Prop           | What it does                                                                              |
|----------------|-------------------------------------------------------------------------------------------|
| `url`          | Where it goes (required)                                                                  |
| `variant`      | `primary` (the default) or `secondary`                                                    |
| `icon`         | Any [icon](#icons), shown before the text                                                 |
| `iconPosition` | `start` (the default) or `end`, to show the icon after the text                           |
| `iconOnly`     | Shows only the icon; the label still names the button for screen readers and as a tooltip |

The label is the button's text, and it's required, even for an
icon-only button. A `url` starting with `/` becomes a full URL, as
Markdown's links do. To put buttons side by side, list them in a
[row](#layout), one per line:

```markdown
:::row
::button[Get started]{url=/start}
::button[Read the docs]{url=/docs variant=secondary}
:::
```

A button also works inside a sentence: `Read the :button[docs]{url=/docs}.`

### Progress and meters

Two built-in components show a number as a bar:

```markdown
::progress[Reading challenge]{value=12 max=50}

::meter[Battery]{value=62 low=20 high=80 optimum=100}
```

| Component  | Use it for                                                             | Props                                                                                                                                                 |
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

Icons are named like components: the built-in ones are `blush/{name}`
(or just `{name}`), and your own are in your theme's or site's
namespace. To add some, put SVG files in a folder:

| Where                                   | Name                                |
|-----------------------------------------|-------------------------------------|
| Your theme's `icons/badge.svg`          | `notebook/badge` (the theme's slug) |
| Your site's `resources/icons/badge.svg` | `app/badge`                         |

To give a built-in icon your own look, add a file named after it in a
`blush` subfolder: a theme's `icons/blush/house.svg`, or your site's
`resources/icons/blush/house.svg`. The site's file wins, then the
active theme's, then its parents'. Draw icons in `currentColor` so they
take the text's color.

Labels shown in lists (and, later, the admin) are translatable, under
`icons.{name}.label` in the same catalog as the namespace's
[component text](#labels-and-translations).

### Inside a sentence

Three built-in components mark up words in running text:

```markdown
A :abbr[CMS]{title="content management system"} saves with :kbd[Ctrl+S].
The launch is :time[next Tuesday]{datetime=2026-10-06}.
```

| Component | Example                                         | Props                                                                                                                                                                                  |
|-----------|-------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `abbr`    | `:abbr[CMS]{title="content management system"}` | `title`: what it stands for.                                                                                                                                                           |
| `kbd`     | `:kbd[Ctrl+S]`                                  | Keys joined with `+` are a combination, and each key is marked up on its own.                                                                                                          |
| `time`    | `:time[next Tuesday]{datetime=2026-10-06}`      | `datetime`: a year (`2026`), month (`2026-10`), date, date and time (`2026-10-06T14:30`, with an optional time zone such as `Z` or `-05:00`), time (`14:30`), or duration (`PT2H30M`). |

`time` gives software (search engines, calendars) the exact date while
readers see your words. On its own line without a label,
`::time{datetime=2026-10-06}` shows the date in your site's language and
time zone, such as "October 6, 2026".

### Good to know

- **Components come from the active theme.** If your content uses a
  component only one theme has, switching themes turns it into plain
  text. For components your content relies on, put them in your site
  instead of a theme (see [Where components live](#where-components-live)).
- **They're cached with the page's content.** A component in Markdown is
  drawn once and kept with the rest of the rendered text, until your content
  changes (a publish) or you switch themes. So a slow one, such as a list
  of every post, is still fast. In development nothing is cached.
- **They can't change the page's `<head>`.** A component used from a
  template can add a stylesheet or meta tag; one used from Markdown
  can't. Load what it needs from the theme instead.
- **You can turn them off** with `directives: false` in
  `config/markdown.php` (see [Configuration](configuration.md#markdown)).
  The syntax then shows as plain text.

## Using components in templates

```php
<?= $template->component('callout', tone: 'tip')->content('<p>Saved!</p>') ?>
```

Props are named arguments. `->content()` fills the component's main content (in
Markdown, the wrapped block or the label). A component can also have
**named slots** for other pieces, filled with `->slot()`:

```php
<?= $template->component('notebook/card', title: 'Hello')
	->content('<p>The body.</p>')
	->slot('footer', '<a href="/more">More</a>') ?>
```

Named slots are for templates only; Markdown fills just the main
content.

## Making a component

### A template

The simplest component is one file in `views/components/`, named
`{namespace}-{name}.php`:

```php
<?php // resources/views/components/app-badge.php

declare(strict_types=1);

$tone = $props['tone'] ?? 'info';

?>
<span class="component-badge component-badge--<?= attr($tone) ?>"><?= raw($slot) ?></span>
```

That's it: `:app/badge[New]{tone=new}` works in Markdown, and
`$template->component('app/badge', tone: 'new')->content('New')` in a
template. In a theme named `notebook`, the same file would be
`views/components/notebook-badge.php`, for `notebook/badge`.

Name a component's classes after it, starting with `component-`: the
built-in ones use `component-callout`, `component-callout--warning`, and
`component-callout__title`, and so on. That keeps them apart from the
rest of a theme's classes.

The file gets:

| Variable  | What it holds                                                                                           |
|-----------|---------------------------------------------------------------------------------------------------------|
| each prop | As its own variable (`$tone`), when the name is a valid one                                             |
| `$props`  | Every prop, including names like `data-id`                                                              |
| `$slot`   | The main content, as HTML (`''` when there's none)                                                      |
| `$slots`  | Named slots: `$slots->footer` is `''` when not filled, and `isset($slots->footer)` tells whether it was |

Props from Markdown are always strings, so check or convert them, as
`$tone` does above.

To change how a built-in component looks, add your own file with its name,
`views/components/callout.php` or `views/components/blush-callout.php`.
Yours wins.

A file in `views/components/` that isn't named for a component (such as a
theme's `badge.php` instead of `notebook-badge.php`) is never drawn;
`component:list` and `theme:check` point it out. Files in subfolders of
`components/` aren't components, so you can keep partials there.

### A component with a PHP class

When a component needs data or logic, such as a list of recent posts, back
it with a class. Its constructor declares the props, and it can also ask
for any of Blush's services:

```php
<?php

declare(strict_types=1);

namespace App\View;

use Blush\Content\ContentRepository;
use Blush\Content\Query\Order;
use Blush\Component\Component;

final class RecentPosts extends Component
{
	public function __construct(
		private readonly ContentRepository $content,
		public int $limit = 5
	) {}

	public function data(): array
	{
		return [
			'posts' => $this->content->query()->type('post')
				->orderBy('published', Order::Desc)->limit($this->limit)->get()
		];
	}
}
```

- **Public properties become the template's variables.** Props from
  Markdown are converted to the types you declare, so `{limit=3}` arrives
  as the integer `3`. A prop typed as a PHP backed enum gets the matching
  case, and a value the enum doesn't have falls back to the default.
  Props the constructor doesn't take stay in `$props`.
- **`data()`** sets the template's variables yourself, as above.
  `$component` is the object itself.
- **`template()`** returns another view to draw with, instead of
  `components/{namespace}-{name}`.
- **`shouldRender()`** returns `false` to draw nothing.
- **`CONTENT`** says what the component wraps: `ComponentContent::None`
  (the default), `Text` (a label), or `Blocks` (a `:::` block).
- **`#[MediaProp]`** on a string parameter makes it a media reference:
  in Markdown, `src=photo.jpg` is found like an image's (next to the
  entry, or in the media folder) and arrives as its full URL.
  **`#[LinkProp]`** marks a link, such as a button's `url`, which becomes
  a full URL when it starts with `/`.

  ```php
  use Blush\Component\MediaProp;

  public function __construct(#[MediaProp] public string $src = '') {}
  ```

The class still needs its template, `views/components/app-recent-posts.php`,
and a name. Register it in a service provider's `boot()` method:

```php
use Blush\Component\ComponentRegistry;

public function boot(): void
{
	$this->container->get(ComponentRegistry::class)->register('app/recent-posts', View\RecentPosts::class);
}
```

`theme:check` warns about a registered class with no template.

### Registering a component

A component with a class must be registered, as above. A template-only
component doesn't have to be, but registering it describes it: what it
wraps and which props it takes, using the same field types as
[content types](content-types.md). Its label then shows in
`component:list`.

```php
use Blush\Content\Schema\Fields\EnumField;
use Blush\Component\ComponentContent;

$components->register(
	'app/badge',
	content: ComponentContent::Text,
	props: [new EnumField('tone', ['info', 'new', 'tip'])->default('info')]
);
```

A class component's props and content come from its constructor and
`CONTENT`, so it needs nothing more.

A provider can also replace a built-in component with its own class:
`register('callout', MyCallout::class)`. The `blush` namespace is only for
the built-in ones, and every other name needs its namespace.

### Labels and translations

A component's label, description, and prop names are translatable text,
kept in the translation catalog of its namespace, under
`components.{name}`:

```json
{
	"components": {
		"badge": {
			"label": "Badge",
			"description": "A short word set apart from the text.",
			"props": {
				"tone": {
					"label": "Tone",
					"choices": { "info": "Info", "new": "New", "tip": "Tip" }
				}
			}
		}
	}
}
```

| Namespace        | Catalog                                          |
|------------------|--------------------------------------------------|
| The theme's slug | The theme's `lang/en.json` (one per language)    |
| `app`            | Your site's `resources/lang/en.json`             |
| A vendor         | Each of that vendor's extensions' `lang/en.json` |

Without a label, one is made from the name (`recent-posts` becomes
"Recent posts"). `theme:check --strict` notes a theme's registered
components that have no label.

## Where components live

| Where                                                                                          | Available                                                                        |
|------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------|
| A theme's `views/components/` (and classes in its `src/`, registered by its provider)          | While that theme or a child of it is active. See [Themes](themes.md#components). |
| Your site's `resources/views/components/` (and classes in `src/`, registered by your provider) | With every theme. See [Extending Blush](extending.md).                           |

Your site's files come first, then the active theme's, then its parents',
then the default theme's. `bin/blush theme:why components/callout` shows
which file is used and what it overrides.

Put a component in a theme when it's part of that theme's design (in the
theme's namespace), and in your site when your content depends on it (in
`app`).
