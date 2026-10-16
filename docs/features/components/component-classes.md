# A component with a class

Give a component a class when it needs typed props, needs Blush's
services, or works something out that the template shouldn't:

```php
<?php // extensions/acme/archives/src/View/PostArchives.php

declare(strict_types=1);

namespace Acme\Archives\View;

use Blush\Component\Component;
use Blush\Content\Entries;

final class PostArchives extends Component
{
	public function __construct(
		private readonly Entries $content,
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

## Registering it

Here it's a [plugin's](../../plugins/README.md) (`acme/archives`, with the
namespace `archives`); in a theme, it's the same, in the theme's `src/`.
Register it in the plugin's or theme's
[service provider's](../../plugins/service-providers.md) `boot()`
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

## What a class can declare

- **Props fill its constructor's parameters by name**, public or not;
  Blush fills the rest (such as `Entries`). Make a prop a
  public property (`public readonly string $by`) when the template
  reads it: `$component->by`. Don't give a prop the name of a service
  parameter, since a prop with that name would be passed instead. A
  prop given as a string is converted to the type you declare (an
  `int`, `float`, `bool`, or backed enum).
- **`Entries` follows the page's language**, so on a
  [translated](../../concepts/translations.md) page it finds that language's
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
- **`ASSETS`** lists the [scripts and styles](../scripts-and-styles.md)
  it needs, by handle (`protected const array ASSETS = ['acme/carousel'];`),
  which load only on pages it's drawn on; override `assets()` to decide
  each time.
- **`modifiers()`** and **`rootAttributes()`** add BEM modifiers and
  other attributes to the root element, and **`$this->messages->t('key', 'Text')`**
  writes text from its own extension's catalogs (a theme's chain,
  or its plugin's), then Blush's, showing the text when none has the key
  (see [Translations](../translations.md)).

A template in the theme chain's `views/components/` always wins over
`render()`.
