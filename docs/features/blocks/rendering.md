# How a block draws itself

Every block class has a `render()` method, which draws it when no
theme in use has a template for it. A template in the theme chain's
`views/blocks/` always wins, so themes restyle a block without touching
its class. This is how a plugin's block works in any theme.

## Rendering itself

`render()` returns one of three things:

- **A template file it ships with**, `$this->view($path)`, with any more
  variables as named arguments: `$this->view($path, columns: 2)`. The
  file is drawn like a theme's template, with `$template` and
  `$block`.
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

## The template

The plugin's own template is the file its `render()` returns: in the
`acme/recent-posts` example from [Making a block](making-a-block.md),
`resources/blocks/recent-posts.php` in the plugin. A theme that
draws it its own way has a file in its `views/blocks/` named
`{namespace}-{name}.php`: here, `views/blocks/acme-recent-posts.php`.

```php
<?php

/**
 * @var Blush\View\Template   $template
 * @var Acme\RecentPosts\RecentPosts  $block
 */

declare(strict_types=1);

?>
<nav <?= $block->attributes() ?>>
	<?php if ($block->heading() !== '') : ?>
		<h2 class="block-recent-posts__title"><?= raw($block->heading()) ?></h2>
	<?php endif ?>
	<ul class="block-recent-posts__list">
		<?php foreach ($block->posts() as $post) : ?>
			<li><a href="<?= url($post->url()) ?>"><?= e($post->title) ?></a></li>
		<?php endforeach ?>
	</ul>
</nav>
```

The `@var` lines are for your editor: with them, it can suggest
`$block->limit` and `$block->posts()` as you type.

The template gets `$block` and `$template` (the
[template helpers](../../themes/templates.md), as in any template).
Everything about the block is on `$block`:

| On `$block`             | What it holds                                                          |
|-----------------------------|------------------------------------------------------------------------|
| Props (`->limit`)           | Plain values, as the constructor typed them                            |
| `->content()`               | The main content, as HTML (`''` when there's none)                     |
| `->variant`                 | The [variant](variants.md), or `'default'`                               |
| Methods (`->heading()`)     | Whatever the class adds                                                |
| `->attributes()`            | The root element's attributes (below)                                  |
| `->prop('data-id')`         | Any prop as given, including ones the constructor doesn't take         |

Props are plain text, so print them with `e()` or `attr()`. Content is
HTML, so print `content()` and methods that return content
(`caption()`, `text()`, `heading()`) with `raw()`.

## Attributes and classes

`$block->attributes()` prints the root element's attributes:

- its classes, named after the block: `block-recent-posts`, plus
  any modifiers (`block-callout--warning`) and the `class` prop
  (`.wide` in Markdown);
- its `id`, from the `id` prop (`#latest` in Markdown);
- and the block's own, such as a callout's `role="note"`.

Pass more to add them: `$block->attributes(['data-open' => true])`.
Name the rest of a block's classes after it, BEM-style, as the
built-in ones do. Ask the block for them rather than writing `__` and
`--` yourself:

| Method | Classes |
|---|---|
| `$block->blockClass()` | `block-callout` |
| `$block->modifierClass('wide')` | `block-callout block-callout--wide` |
| `$block->elementClass('title')` | `block-callout__title` |
| `$block->elementClass('item')->modifier('current')` | `block-menu__item block-menu__item--current` |
| `$block->elementClass('item')->when($open, 'open')` | `block-menu__item--open` added only when `$open` |
| `$block->elementClass('item')->with('extra')` | `block-menu__item extra` |

Each returns a class you can keep chaining, and it prints as the full
list wherever a string goes (`attr()`, `self::html()`).

```php
<p class="<?= attr($block->elementClass('title')) ?>"><?= raw($block->heading()) ?></p>
```

That keeps them apart from the rest of a theme's classes. In a block's
class, it's `$this->elementClass('title')`. Names are letters, digits,
hyphens, and underscores. See also
[BEM classes](../../themes/bem.md).

## Changing a built-in block's look

To change how a built-in block looks, add your own template with its
name, `views/blocks/callout.php` or `views/blocks/core-callout.php`.
Yours wins. The built-in blocks draw themselves with Blush's own
templates, in `resources/blocks/` (the class's `render()`), and
your template gets the same `$block` (their classes are in
`Blush\Block`): start from Blush's file for the one you're
changing.

A file in `views/blocks/` that isn't for a registered block
(such as a theme's `tabs.php` for a block no plugin adds) is never
drawn; `block:list` and `theme:check` point it out. Files in
subfolders of `blocks/` aren't blocks, so you can keep partials
there. Blush's `menu` and `toc` draw their nested lists inside their own
template, so to change the lists, override the whole block.

`theme:check` warns about a registered class with no template and no
markup of its own.
