# Components

A component is a reusable piece of a template: a card, a post header, a
byline, a list of archives. A theme builds its pages from components so
it never writes the same markup twice, and a child theme can change one
piece everywhere at once.

Components belong to templates, never to content. What your content
says, such as a callout or a gallery, is a [directive](directives.md).
A template can draw either one.

## Component names

A component's name has two parts, `{namespace}/{name}`, and is always
written in full:

| Namespace              | Whose components | Example             |
|------------------------|------------------|---------------------|
| The theme's namespace  | A theme's own    | `notebook/card`     |
| The plugin's namespace | A plugin's own   | `newsletter/signup` |

A theme or [plugin](extending.md#plugins) declares its namespace in its
manifest (`"namespace": "notebook"`). There are no built-in components,
and a site has none of its own: components come from themes and
plugins.

## Using components in templates

```php
<?= $template->component('notebook/card', entry: $post) ?>
```

Props are named arguments. `->content()` fills the component's main
content, and **named slots** hold other pieces, filled with `->slot()`:

```php
<?= $template->component('notebook/card', entry: $post)
	->content('<p>The body.</p>')
	->slot('footer', '<a href="/more">More</a>') ?>
```

A [region](menus.md#regions) can show one too, with its props as the
item's other keys:

```yaml
- component: newsletter/signup
  title: Stay in touch
```

To see every component the active theme can draw, run:

```sh
bin/blush component:list
```

## A template-only component

Most components are just a template in `views/components/`, named
`{namespace}-{name}.php`. In a theme named `notebook`, the `notebook/card`
component is `views/components/notebook-card.php`:

```php
<?php

/**
 * @var Blush\View\Template                $template
 * @var Blush\Component\TemplateComponent  $component
 */

declare(strict_types=1);

$entry = $component->prop('entry');

?>
<article <?= $component->attributes() ?>>
	<h2 class="component-card__title"><?= e($entry->title) ?></h2>
	<?= raw($component->content()) ?>
	<?php if ($component->slots->has('footer')) : ?>
		<footer class="component-card__footer"><?= raw($component->slots->footer) ?></footer>
	<?php endif ?>
</article>
```

There's nothing to register. Everything about the component is on
`$component`:

| On `$component`          | What it holds                                                     |
|--------------------------|-------------------------------------------------------------------|
| `->prop('entry')`        | A prop as given, or a default: `->prop('size', 'small')`          |
| `->content()`            | The main content, as HTML (`''` when there's none)                |
| `->slots->footer`        | A named slot, as HTML (`''` when it wasn't filled)                |
| `->slots->has('footer')` | Whether a named slot was filled                                   |
| `->attributes()`         | The root element's attributes: `class="component-card"`, and more |

`$component->attributes()` prints its class, named after the component
(`component-card`), plus the `class` and `id` props. Name the rest of its
classes after it, BEM-style (`component-card__title`).

A file in `views/components/` that isn't named for a component (such as
a theme's `card.php` instead of `notebook-card.php`) is never drawn;
`component:list` and `theme:check` point it out. Files in subfolders of
`components/` aren't components, so you can keep partials there.

## A component with a class

Give a component a class when it needs typed props, needs Blush's
services, or works something out that the template shouldn't:

```php
<?php // extensions/acme/archives/src/View/PostArchives.php

declare(strict_types=1);

namespace Acme\Archives\View;

use Blush\Component\Component;
use Blush\Content\ContentRepository;

final class PostArchives extends Component
{
	public function __construct(
		private readonly ContentRepository $content,
		public readonly string $by = 'year'
	) {}

	/**
	 * @return array<string, int>
	 */
	public function counts(): array
	{
		// …
	}
}
```

Here it's a [plugin's](extending.md#plugins) (`acme/archives`, with the
namespace `archives`); in a theme, it's the same, in the theme's `src/`.
Register it in the plugin's or theme's service provider's `boot()`
method:

```php
use Blush\Component\ComponentRegistry;

public function boot(): void
{
	$this->container->get(ComponentRegistry::class)->register('archives/post-archives', View\PostArchives::class);
}
```

A theme's template for it is `views/components/archives-post-archives.php`,
found by its name like any other.

- **Props fill its constructor's parameters by name**, public or not;
  Blush fills the rest (such as the `ContentRepository`). Make a prop a
  public property (`public readonly string $by`) when the template
  reads it: `$component->by`. Don't give a prop the name of a service
  parameter, since a prop with that name would be passed instead. A
  prop given as a string is converted to the type you declare (an
  `int`, `float`, `bool`, or backed enum).
- **The `ContentRepository` follows the page's language**, so on a
  [translated](content.md#translations) page it finds that language's
  entries.
- **`render()`** is optional: the component's own markup, used only when
  no theme in the chain has a template for it. It returns a template
  file (`$this->view($path)`) or HTML as a string (escape what goes in
  it). A theme's components don't need it: their templates are in the
  theme. A plugin's do, since its markup comes from nowhere else:
  return a template file in the plugin, such as
  `$this->view(dirname(__DIR__, 2) . '/resources/components/post-archives.php')`,
  which a theme's template replaces. `theme:check` and `component:list` point out a
  component with no template and no `render()`.
- **`shouldRender()`** returns `false` to draw nothing, and
  **`template()`** returns another view to draw with.
- **`ASSETS`** lists the [scripts and styles](extending.md#scripts-and-styles)
  it needs, by handle (`protected const array ASSETS = ['acme/carousel'];`),
  which load only on pages it's drawn on; override `assets()` to decide
  each time.
- **`modifiers()`** and **`rootAttributes()`** add BEM modifiers and
  other attributes to the root element, and **`$this->t('key')`**
  translates text from the theme's catalog.

A template in the theme chain's `views/components/` always wins over
`render()`.

## Where components live

| Where                                                                                          | Available                                                                        |
|------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------|
| A theme's `views/components/` (and classes in its `src/`, registered by its provider)          | While that theme or a child of it is active. See [Themes](themes.md#directives-and-components). |
| A plugin (its classes, registered by its provider)                                             | While it's on, for themes to use. See [Extending Blush](extending.md#directives-and-components-from-a-plugin). |

The active theme's files come first, then its parents', and last the
component's own `render()`. To change a theme's component, make a
[child theme](themes.md#overriding-templates) with its own template.
`bin/blush theme:why components/notebook-card` shows which file is used
and what it overrides.
