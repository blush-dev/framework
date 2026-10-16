# Blocks and components in a theme

Your theme draws two kinds of pieces:

- **[Blocks](../concepts/blocks/README.md)** are what content says: callouts,
  galleries, buttons. They come from Blush and plugins, never a
  theme, so content never depends on one theme. Your theme gives them
  their look.
- **[Components](../features/components/README.md)** are your theme's own building blocks
  for its templates: a card, a post header, a list of archives. Content
  never names them.

## Giving blocks a look

The built-in blocks work in every theme, drawing themselves with
Blush's templates: `callout`, `embed`, `figure`, and `gallery`; the
layouts `group`, `grid`, `row`, and `stack`; the media blocks `audio`,
`video`, `file`, and `playlist`; the inline ones `abbr`, `badge`,
`button`, `cite`, `dfn`, `icon`, `ins`, `kbd`, `samp`, `small`, `time`,
and `var`; `toc`, a table of contents; `menu`; and `progress` and
`meter`. To change how one looks, style its classes
(`.block-callout`, `.block-callout__title`), or add your own
`views/blocks/callout.php` (or `core-callout.php`, and so on);
yours wins. A plugin's block is drawn the same way:
`views/blocks/{namespace}-{name}.php`.

To give a block a style of your own, add a
[variant](../features/blocks/README.md) in `theme.json` and style its class
(`.block-callout--bordered`), or give it a template of its own
(`views/blocks/callout-bordered.php`). A template in `blocks/`
for a block no one registered is never drawn: a theme can't add
blocks, so make it a component instead.

## Your own components

A theme's components are in its namespace, which its `theme.json`
declares: the `notebook` namespace's card is `notebook/card`. A
template-only component is a file in `views/components/` named
`{namespace}-{name}.php`, such as `views/components/notebook-card.php`.
Use it in a template with
`<?= $template->component('notebook/card', entry: $entry) ?>`.

## A component with a class

A component that needs data, such as a list of posts, can have a PHP
class. Keep it in the theme's `src/`, and name a provider and autoload
map in `theme.json`:

```json
{
	"name": "acme/notebook",
	"label": "Notebook",
	"namespace": "notebook",
	"provider": "Notebook\\ThemeProvider",
	"autoload": { "psr-4": { "Notebook\\": "src/" } }
}
```

The provider registers the class in its `boot()` method, and the
template in `views/components/` (here, `notebook-recent-posts.php`) draws
it, so the class needs no `render()`; a child theme can override the
template with its own:

```php
<?php // src/ThemeProvider.php

declare(strict_types=1);

namespace Notebook;

use Blush\Core\ServiceProvider;
use Blush\Component\ComponentRegistry;

final class ThemeProvider extends ServiceProvider
{
	public function boot(): void
	{
		$this->container->get(ComponentRegistry::class)->register('notebook/recent-posts', View\RecentPosts::class);
	}
}
```

See [Components](../features/components/README.md) for writing the class.

## Finding and checking them

`bin/blush block:list` lists every block and the file that draws
each, and `bin/blush component:list` your theme's components.
`bin/blush theme:why blocks/callout` shows what a file overrides.
`theme:check` warns about a block or component with a PHP class but
no template, about a file in `blocks/` for no registered block
or in `components/` not named for a component, about variants in
`theme.json` for blocks that don't exist or with names that aren't
valid, and about a variant's template that's also another block's.
With `--strict`, it also notes variants without a translated label (see
[Blocks](../features/blocks/README.md) for labels and translations).
