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
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::

::embed[Our launch]{url="https://youtu.be/…" title="Launch video"}

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
| `callout` | `:::callout[Title]{variant=info}` … `:::`              | [Variants](#variants): `info`, `tip`, `warning`, or `danger`; without one, it's a plain note. The label is the title.                                                                                                                                                  |
| `gallery` | `:::gallery{columns=3}` … `:::`                        | `columns`: 1 to 6 (default 3). `layout`: `flex` (default; rows that grow to fill the width) or `grid` (even columns). Wrap images in it.                                                                                                                               |
| `figure`  | `:::figure[Caption]` … `:::`                           | Sets anything apart with a caption (the label): an image, a table, a code block, a quote. An image on its own line inside it is just the image. For a lone image, you don't need it: an image on its own line is already a figure, with its quoted title as the caption. |
| `embed`   | `::embed[Caption]{url="https://youtu.be/…" title="…"}` | `url`, `title`. YouTube and Vimeo (and [providers you add](configuration.md#embeds)) play in a frame at the video's real shape, named by its own title; YouTube and Vimeo in privacy-friendly mode, from the URL's start time (`?t=90`). Any other URL becomes a link. |

Themes can restyle these or add their own.

### Layout

Three built-in components arrange other blocks. Their layout works in
every theme; themes style them further through their `component-group`,
`component-grid`, and `component-row` classes.

| Component | Example                           | Props                                                                                                                                                          |
|-----------|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `group`   | `:::group{.highlight}` … `:::`    | Use it to put a class or id on several blocks.                                                                                                                 |
| `grid`    | `:::grid{columns=3}` … `:::`      | `columns`: the most columns, 1 to 12 (default 2). `min`: the narrowest a column gets before the grid drops a column (default `12rem`; `0` never drops). `gap`. |
| `row`     | `:::row{justify=between}` … `:::` | `justify`: `start` (default), `center`, `end`, or `between`. `align`: `center` (default), `start`, `end`, `stretch`, or `baseline`. `wrap=false`. `gap`.       |

All three also take `tag`, the element they render as: `div` (the
default), `section`, or `aside`. A section or aside is a landmark, so
give it a label to name it for screen readers:

```markdown
:::grid[Related reading]{tag=aside columns=3}
…
:::
```

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
same way as an image's: `/media/…` is in your media folder (the first
`/` is optional, so `media/song.mp3` works too). Like
Markdown's own links, they become full URLs (`https://example.com/media/song.mp3`),
so they still work in feeds.

| Component | Example                                                                     | Props                                                                                                                                                                                                             |
|-----------|-----------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `audio`   | `::audio[Episode 12]{src=/media/episode.mp3}`                               | `src`. `preload`: `metadata` (default), `none`, or `auto`. `loop`. The label is the caption.                                                                                                                      |
| `video`   | `::video[Launch day]{src=/media/launch.mp4 poster=/media/launch.jpg}`       | `src`, `poster` (an image shown before it plays), `track` (a WebVTT captions file, in your site's language). `width` and `height`, which default to the poster's. `preload`, `loop`, `muted`. The label is the caption. |
| `file`    | `::file[The annual report]{src=/media/report.pdf}`                          | `src`. The label is the link text (the file's name without one). It shows the file's type, and its size when it's in your media folder.                                                                           |

Only the [file types your site allows](media.md#allowed-file-types) are
served, so check the list before offering other kinds of files for
download.

### Menus

`::menu{name=social}` shows one of your site's [menus](menus.md), by the
theme location that shows it. `label` names the navigation for screen
readers. In content, no link is marked as the current page, since the
same content is shown on every page. Themes usually print menus from
their templates instead:
`<?= $template->component('menu', name: 'primary') ?>`.

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
| `icon`         | Any [icon](#icons), shown before the text                                                 |
| `iconPosition` | `start` (the default) or `end`, to show the icon after the text                           |
| `iconOnly`     | Shows only the icon; the label still names the button for screen readers and as a tooltip |

The label is the button's text, and it's required, even for an
icon-only button. Without a [variant](#variants) it's the main, filled
button; `variant=secondary` is an outlined one, for an action beside the
main one. A `url` starting with `/` becomes a full URL, as
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
<?= $template->component('callout', variant: 'tip')->content('<p>Saved!</p>') ?>
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
content. The component's template reads them back as
`$component->content()` and `$component->slots->footer`.

When the content is text, such as a button's text, you can pass it as
`label` instead, and it's escaped for you:
`$template->component('button', url: '/start', label: 'Start & go')`.

## Variants

A variant is a named style of a component: `variant=warning` on a
callout, `variant=secondary` on a button. The component decides what the
thing is; the variant decides how it looks.

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::
```

- **Every component has Default,** which is what you get without
  `variant` (or with `variant=default`). It's always there, and no theme
  or extension can rename or replace it, so content that has never been
  given a variant keeps following the theme.
- **A variant the component doesn't have renders as Default,** such as
  one from a theme that isn't active. `content:lint` warns about these.
- **Variants belong to whoever declares them.** The built-in components
  come with a few; a theme, your site, or an extension can add more to
  any component. A theme's apply only while it (or a child of it) is
  active.

The editor in the admin shows a component's variants in a list at the
top of its options, each with its description.

### Styling a variant

A variant adds a class to the component's root element:
`component-{name}--{variant}`, such as `component-callout--warning`. In a
template, `$component->variant` is the variant's name (`'default'` for
none), and `$component->isVariant('warning')` checks it.

When a variant needs more than a class, give it its own template:
`views/components/callout-bordered.php` draws a callout with
`variant=bordered`, and the component's own template draws the rest.
(For a theme's own component, that's `{slug}-{name}-{variant}.php`.)
Variants are meant mostly for looks, but nothing stops one from doing
more.

### Adding variants

A component class lists its own in its `VARIANTS` constant:

```php
final class Card extends Component
{
	public const array VARIANTS = ['wide', 'plain'];
}
```

A template-only component lists them when it's registered:
`$components->register('app/badge', variants: ['outline'])`.

A theme adds variants to any component in its `theme.json`, by
component:

```json
{
	"name": "Notebook",
	"variants": {
		"callout": ["bordered", "compact"],
		"notebook/card": ["wide"]
	}
}
```

Your site, an extension, or a theme's provider can add (or remove)
variants from PHP, by listening for `ComponentVariantsCollecting`. It
fires once per component, the first time its variants are needed, so the
order providers boot in doesn't matter. The second argument is the
namespace whose catalog has the variant's text:

```php
use Blush\Component\Events\ComponentVariantsCollecting;
use Blush\Event\Listener\ListenerRegistry;

$this->container->get(ListenerRegistry::class)->listen(
	ComponentVariantsCollecting::class,
	function (ComponentVariantsCollecting $event): void {
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

## Making a component

A component is a PHP class for its props and logic, plus a template that
draws it. (A component can also be [just a template](#a-template-only-component).)

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
use Blush\Component\Component;
use Blush\Component\ComponentContent;

final class RecentPosts extends Component
{
	public const ComponentContent CONTENT = ComponentContent::Text;

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
}
```

- **Props are typed.** Props from Markdown are always strings, so Blush
  converts them to the types you declare: `{limit=3}` arrives as the
  integer `3`. A prop typed as a PHP backed enum gets the matching case,
  and a value the enum doesn't have falls back to the default.
- **`label`** gets the Markdown label (`::app/recent-posts[Latest]`), if
  the constructor takes it.
- **Name the content for its role.** A component's main content is
  `$this->content()`: a `:::` block's HTML, or the escaped label for `::`
  and `:`. When it has a role, such as a caption or a title, add a method
  with that name. `$this->contentOr($this->label)` returns the content,
  or else the label escaped, so it works whether a template passes
  `->content()` or `label:`.
- **Methods do the work**, such as `posts()` above, so the template doesn't
  have to.
- **`shouldRender()`** returns `false` to draw nothing.
- **`template()`** returns another view to draw with, instead of
  `components/{namespace}-{name}`.
- **`CONTENT`** says what the component wraps: `ComponentContent::None`
  (the default), `Text` (a label), or `Blocks` (a `:::` block).
- **`modifiers()`** returns BEM modifiers for the root element, such as
  `['warning']` for `component-callout--warning`.
- **`rootAttributes()`** returns other attributes for the root element,
  such as `['role' => 'note']`.
- **Inner elements can have attribute methods too**, such as the embed's
  `frameAttributes()` for its `<iframe>`. Build them with `self::html([...])`, which
  escapes each value (URL attributes such as `src` and `href` as URLs,
  leaving out unsafe ones), leaves out `null`, `false`, and `''`, and
  prints `true` as the name alone. Include the element's class
  (`$this->block() . '__wrapper'`), so the template is just
  `<div <?= $component->wrapperAttributes() ?>>`.
- **`$this->t('key', name: 'value')`** translates text from the theme's
  catalog, as `$template->t()` does.
- **`#[MediaProp]`** on a string parameter makes it a media reference:
  in Markdown, `src=photo.jpg` is found like an image's (next to the
  entry, or in the media folder) and arrives as its full URL.
  **`#[LinkProp]`** marks a link, such as a button's `url`, which becomes
  a full URL when it starts with `/`.

  ```php
  use Blush\Component\MediaProp;

  public function __construct(#[MediaProp] public readonly string $src = '') {}
  ```

Register the class in a service provider's `boot()` method:

```php
use Blush\Component\ComponentRegistry;

public function boot(): void
{
	$this->container->get(ComponentRegistry::class)->register('app/recent-posts', View\RecentPosts::class);
}
```

### The template

The template is a file in `views/components/` named
`{namespace}-{name}.php`: here, `resources/views/components/app-recent-posts.php`.
In a theme named `notebook`, a `notebook/recent-posts` component's
would be `views/components/notebook-recent-posts.php`.

```php
<?php

/**
 * @var Blush\View\Template   $template
 * @var App\View\RecentPosts  $component
 */

declare(strict_types=1);

?>
<nav <?= $component->attributes() ?>>
	<?php if ($component->heading() !== '') : ?>
		<h2 class="component-recent-posts__title"><?= raw($component->heading()) ?></h2>
	<?php endif ?>
	<ul class="component-recent-posts__list">
		<?php foreach ($component->posts() as $post) : ?>
			<li><a href="<?= url($template->permalink($post)) ?>"><?= e($post->title) ?></a></li>
		<?php endforeach ?>
	</ul>
</nav>
```

The `@var` lines are for your editor: with them, it can suggest
`$component->limit` and `$component->posts()` as you type.

The template gets `$component` and `$template` (the
[template helpers](themes.md#templates), as in any template).
Everything about the component is on `$component`:

| On `$component`             | What it holds                                                          |
|-----------------------------|------------------------------------------------------------------------|
| Props (`->limit`)           | Plain values, as the constructor typed them                            |
| `->content()`               | The main content, as HTML (`''` when there's none)                     |
| `->slots->footer`           | A named slot, as HTML (`''` when it wasn't filled)                     |
| `->slots->has('footer')`    | Whether a named slot was filled                                        |
| Methods (`->heading()`)     | Whatever the class adds                                                |
| `->attributes()`            | The root element's attributes (below)                                  |
| `->prop('data-id')`         | Any prop as given, including ones the constructor doesn't take         |

Props are plain text, so print them with `e()` or `attr()`. Content is
HTML, so print `content()`, slots, and methods that return content
(`caption()`, `text()`, `heading()`) with `raw()`.

`$component->attributes()` prints the root element's attributes:

- its classes, named after the component: `component-recent-posts`, plus
  any modifiers (`component-callout--warning`) and the `class` prop
  (`.wide` in Markdown);
- its `id`, from the `id` prop (`#latest` in Markdown);
- and the component's own, such as a callout's `role="note"`.

Pass more to add them: `$component->attributes(['data-open' => true])`.
Name the rest of a component's classes after it, BEM-style, as the
built-in ones do (`component-callout__title`). That keeps them apart from
the rest of a theme's classes.

To change how a built-in component looks, add your own template with its
name, `views/components/callout.php` or `views/components/blush-callout.php`.
Yours wins. The built-in components' classes are in `Blush\Component`, so
your template gets the same `$component`: see the default theme's
templates in `resources/themes/default/views/components/` for what each one
uses.

A file in `views/components/` that isn't named for a component (such as a
theme's `badge.php` instead of `notebook-badge.php`) is never drawn;
`component:list` and `theme:check` point it out. Files in subfolders of
`components/` aren't components, so you can keep partials there (the
default theme's `toc` draws its nested lists with
`components/toc/list.php`).

`theme:check` warns about a registered class with no template.

### A template-only component

A simple component can be just its template, with no class:

```php
<?php // resources/views/components/app-badge.php

/**
 * @var Blush\Component\TemplateComponent $component
 */

declare(strict_types=1);

?>
<span <?= $component->attributes() ?>><?= raw($component->content()) ?></span>
```

That's it: `:app/badge[New]` works in Markdown, and
`$template->component('app/badge')->content('New')` in a template.

Its `$component` has no props of its own, so read them with
`$component->prop('tone', 'info')`, which returns the prop as given (in
Markdown, always a string) or the default. Check what you get, or give the
component a class once it needs typed props.

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

A component's label, description, prop names, and variants are
translatable text, kept in the translation catalog of its namespace,
under `components.{name}`:

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

| Namespace        | Catalog                                          |
|------------------|--------------------------------------------------|
| The theme's slug | The theme's `lang/en.json` (one per language)    |
| `app`            | Your site's `resources/lang/en.json`             |
| A vendor         | Each of that vendor's extensions' `lang/en.json` |

A variant someone else adds to your component, such as a theme's
`bordered` callout, has its text in their catalog, under the same
`components.{name}.variants.{variant}` key.

Without a label, one is made from the name (`recent-posts` becomes
"Recent posts"). `theme:check --strict` notes a theme's registered
components, and the variants in its `theme.json`, that have no label.

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
