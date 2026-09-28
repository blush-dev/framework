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

These four work in every theme, because the default theme provides them:

| Component | Example                                                | Props                                                                                             |
|-----------|--------------------------------------------------------|---------------------------------------------------------------------------------------------------|
| `callout` | `:::callout[Title]{tone=info}` … `:::`                 | `tone`: `note` (default), `info`, `tip`, `warning`, or `danger`. The label is the title.          |
| `gallery` | `:::gallery{columns=3}` … `:::`                        | `columns`: 1 to 6 (default 3). Wrap images in it.                                                 |
| `figure`  | `::figure[Caption]{src="/media/a.jpg" alt="…"}`        | `src`, `alt`. The label is the caption.                                                           |
| `embed`   | `::embed[Caption]{url="https://youtu.be/…" title="…"}` | `url`, `title`. YouTube and Vimeo play in a privacy-friendly frame; any other URL becomes a link. |

Themes can restyle these or add their own.

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
<span class="badge badge--<?= attr($tone) ?>"><?= raw($slot) ?></span>
```

That's it: `:app/badge[New]{tone=new}` works in Markdown, and
`$template->component('app/badge', tone: 'new')->content('New')` in a
template. In a theme named `notebook`, the same file would be
`views/components/notebook-badge.php`, for `notebook/badge`.

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
use Blush\View\Component\Component;

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

The class still needs its template, `views/components/app-recent-posts.php`,
and a name. Register it in a service provider's `boot()` method:

```php
use Blush\View\Component\ComponentRegistry;

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
use Blush\View\Component\ComponentContent;

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
