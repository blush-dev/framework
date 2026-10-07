# Components

A component is a reusable piece of a template: a card, a post header, a
byline, a list of archives. A theme builds its pages from components so
it never writes the same markup twice, and a child theme or your site
can change one piece everywhere at once.

Components belong to templates, never to content. What your content
says, such as a callout or a gallery, is a [directive](directives.md).
A template can draw either one.

## Component names

A component's name has two parts, `{namespace}/{name}`, and is always
written in full:

| Namespace              | Whose components | Example             |
|------------------------|------------------|---------------------|
| The theme's namespace  | A theme's own    | `notebook/card`     |
| `app`                  | Your site's own  | `app/post-header`   |
| The plugin's namespace | A plugin's own   | `newsletter/signup` |

A theme or [plugin](extending.md#plugins) declares its namespace in its
manifest (`"namespace": "notebook"`). There are no built-in components.

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
<?php // src/View/PostArchives.php

declare(strict_types=1);

namespace App\View;

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

Its template is `resources/views/components/app-post-archives.php`,
found by its name like any other.

Register it in a service provider's `boot()` method:

```php
use Blush\Component\ComponentRegistry;

public function boot(): void
{
	$this->container->get(ComponentRegistry::class)->register('app/post-archives', View\PostArchives::class);
}
```

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
  neither your site nor the theme has a template for it. It returns a
  template file (`$this->view($path)`) or HTML as a string (escape what
  goes in it). A theme's components don't need it: their templates are
  in the theme. A plugin's do, since its markup comes from nowhere else,
  and so does a site component that themes may restyle: a template in
  your site's `views/` comes first and would always win, so keep that
  default somewhere else (such as `resources/components/`) and return it
  from `render()`. `theme:check` and `component:list` point out a
  component with no template and no `render()`.
- **`shouldRender()`** returns `false` to draw nothing, and
  **`template()`** returns another view to draw with.
- **`modifiers()`** and **`rootAttributes()`** add BEM modifiers and
  other attributes to the root element, and **`$this->t('key')`**
  translates text from the theme's catalog.

A template in the theme chain or your site's `views/components/` always
wins over `render()`.

## Where components live

| Where                                                                                          | Available                                                                        |
|------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------|
| A theme's `views/components/` (and classes in its `src/`, registered by its provider)          | While that theme or a child of it is active. See [Themes](themes.md#directives-and-components). |
| Your site's `resources/views/components/` (and classes in `src/`, registered by your provider) | With every theme. See [Extending Blush](extending.md).                           |
| A plugin (its classes, registered by its provider)                                             | While it's on, for themes to use.                                                |

Your site's files come first, then the active theme's, then its
parents', and last the component's own `render()`.
`bin/blush theme:why components/notebook-card` shows which file is used
and what it overrides.
