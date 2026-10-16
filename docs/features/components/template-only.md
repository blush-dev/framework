# A template-only component

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

## Classes

`$component->attributes()` prints its class, named after the component
(`component-card`), plus the `class` and `id` props. Name the rest of its
classes after it, BEM-style, with `$component->elementClass('title')`
(`component-card__title`), which chains:
`$component->elementClass('title')->modifier('large')`. See
[BEM classes](../../themes/bem.md).

## Files that aren't components

A file in `views/components/` that isn't named for a component (such as
a theme's `card.php` instead of `notebook-card.php`) is never drawn;
`component:list` and `theme:check` point it out. Files in subfolders of
`components/` aren't components, so you can keep partials there.
