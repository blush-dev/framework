# Template engines

Templates are plain PHP, but a plugin can add another template
language, such as Twig.

## Writing an engine

Write a class that implements `Blush\View\Engine\ViewEngine`, which
turns one file into HTML:

```php
use Blush\View\Engine\ViewEngine;
use Blush\View\Template;

final class TwigEngine implements ViewEngine
{
	public function render(string $file, array $data, Template $template): string
	{
		// Render $file with $data, and $template as a global.
	}
}
```

Register it for a file extension (without the dot) in the plugin's
[provider's](../plugins/service-providers.md) `boot()`:

```php
use Blush\View\Engine\ViewEngineRegistry;

public function boot(): void
{
	$this->container->get(ViewEngineRegistry::class)->register('twig', TwigEngine::class);
}
```

## How files are matched

Themes can then use `single.twig` alongside `single.php`. A file goes to
the engine for the longest extension it ends with (`card.blade.php` to
`blade.php` before `php`). Templates in different engines can include
each other, since every include goes back through Blush. When one folder
has the same template in two engines, PHP's wins.

## Giving templates the template API

Give templates `$template`'s methods, the same API PHP templates have.
Use `$template->setSection('name', $html)` for a section your engine
builds with its own blocks, and `$template->layout('base')` to wrap a
template in a layout.

If your engine escapes output on its own, don't escape Blush's HTML a
second time: methods marked with the `Blush\View\ReturnsHtml` attribute
(`include()`, `section()`, `component()`, `block()`, and others) return
HTML, and so does any value that implements `Blush\View\SafeHtml`.
