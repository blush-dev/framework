# Making a block

A block is a PHP class for its props and logic, plus a template that
draws it. Every class draws itself by default with its `render()`
method, and a theme draws it its own way with a template. Every
block has a class. Make one in a [plugin](../../plugins/README.md); a
theme can't register one. The examples here are from a plugin named
`acme/recent-posts`, with the namespace `acme`.

## The class

The constructor declares the props as public properties, and can also ask
for any of Blush's services:

```php
<?php // extensions/acme/recent-posts/src/RecentPosts.php

declare(strict_types=1);

namespace Acme\RecentPosts;

use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Order;
use Blush\Block\Block;
use Blush\Block\BlockContent;
use Blush\Block\BlockView;
use Blush\Block\BlockKind;

final class RecentPosts extends Block
{
	public const BlockContent CONTENT = BlockContent::Text;

	public const ?BlockKind KIND = BlockKind::Leaf;

	public function __construct(
		private readonly Entries $content,
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
			->orderBy('date', Order::Desc)->limit($this->limit)->get();
	}

	/**
	 * Draws it when the theme has no template for it.
	 */
	public function render(): BlockView
	{
		return $this->view(dirname(__DIR__) . '/resources/blocks/recent-posts.php');
	}
}
```

## What a class can declare

- **Props are typed.** Props from Markdown are always strings, so Blush
  converts them to the types you declare: `{limit=3}` arrives as the
  integer `3`. A prop typed as a PHP backed enum gets the matching case,
  and a value the enum doesn't have falls back to the default.
- **`label`** gets the Markdown label (`::acme/recent-posts[Latest]`), if
  the constructor takes it.
- **`Entries` follows the page's language.** On a
  [translated](../../concepts/translations.md) page, such as `/fr/a-propos`,
  `$this->content->query()` finds French entries and `term()` finds French
  topics, so `posts()` above lists the French posts, whether a template
  or a translation's Markdown uses the block. Ask for another
  language with `->language('en')`, or every language with
  `->anyLanguage()`. When the site's `untranslated` setting is
  `'include'`, French lists add the originals of untranslated entries;
  `->withOriginals()` asks for them whatever the setting, and
  `->withOriginals(false)` never does. See also
  [Finding entries](../finding-entries.md).
- **Name the content for its role.** A block's main content is
  `$this->content()`: a `:::` block's HTML, or the escaped label for `::`
  and `:`. When it has a role, such as a caption or a title, add a method
  with that name. `$this->contentOr($this->label)` returns the content,
  or else the label escaped, so it works whether a template passes
  `->content()` or `label:`.
- **Methods do the work**, such as `posts()` above, so the template doesn't
  have to.
- **`render()`** is required: the block's own markup. See
  [How a block draws itself](rendering.md).
- **`shouldRender()`** returns `false` to draw nothing.
- **`template()`** returns another view to draw with, instead of
  `blocks/{namespace}-{name}`.
- **`CONTENT`** says what the block wraps: `BlockContent::None`
  (the default), `Text` (a label), or `Blocks` (a `:::` block).
- **`KIND`** is required: how it's written, the only way it works.
  `BlockKind::Container` (`:::`), `Leaf` (`::`, on a line of its
  own), or `Inline` (`:`, in a sentence), from `Blush\Block`.
  Registering a block without one fails, and so does one whose
  `KIND` and `CONTENT` disagree: only a block that wraps blocks
  (`Blocks`) is a container, and one that does is always a container.
- **`HOLDS`** says what a `:::` block takes, when it's only some things:
  `['image']` for Markdown images, or blocks' full names. The
  admin's editor then offers only those inside it (the gallery holds
  images). The site renders only those inside it, leaving out the
  rest: text, other Markdown, and blocks it doesn't list. The images
  in a paragraph with text are kept, each a figure.
- **`$this->container`** is the `:::` block it's directly inside,
  or `null`, and `$this->isIn('playlist')` asks by name (a full name, or
  a built-in's short name). A block can draw itself differently
  there: `audio` in a playlist is one of its rows.
- **`modifiers()`** returns BEM modifiers for the root element, such as
  `['warning']` for `block-callout--warning`.
- **`rootAttributes()`** returns other attributes for the root element,
  such as `['role' => 'note']`.
- **Inner elements can have attribute methods too**, such as the embed's
  `frameAttributes()` for its `<iframe>`. Build them with `self::html([...])`, which
  escapes each value (URL attributes such as `src` and `href` as URLs,
  leaving out unsafe ones), leaves out `null`, `false`, and `''`, and
  prints `true` as the name alone. Include the element's class
  (`$this->elementClass('wrapper')`), so the template is just
  `<div <?= $block->wrapperAttributes() ?>>`.
- **`ASSETS`** lists the [scripts and styles](../scripts-and-styles.md)
  it needs on the page, by handle: `protected const array ASSETS = ['acme/tabs'];`.
  They load only on pages where it's drawn, in an entry's text too.
  Override `assets()` to decide each time it's drawn.
- **`$this->messages->t('key', 'Text with {name}', name: 'value')`** writes
  text, as a template's `$messages->t()` does, printed in the page's language: from its own
  extension's catalogs (its plugin's, or a theme's chain), then Blush's,
  showing the text when none has the key (see
  [Translations](../translations.md)).
- **`#[MediaProp]`** on a string parameter makes it a media reference:
  in Markdown, `src=photo.jpg` is found like an image's (next to the
  entry, or in the media folder) and arrives as its full URL.
  **`#[LinkProp]`** marks a link, such as a button's `url`, which becomes
  a full URL when it starts with `/`.

  ```php
  use Blush\Block\MediaProp;

  public function __construct(#[MediaProp] public readonly string $src = '') {}
  ```

  Name the kind of file it plays, `#[MediaProp(MediaKind::Video)]`
  (`Blush\Media\MediaKind`: `Image`, `Video`, `Audio`, or `File`), and
  the admin's picker offers only that kind for it. Leave it out for a
  prop that takes any file, such as a download.

## Registering it

Register the class in the plugin's [service provider's](../../plugins/service-providers.md)
`boot()` method:

```php
use Blush\Block\BlockRegistry;

public function boot(): void
{
	$this->container->get(BlockRegistry::class)->register('acme/recent-posts', RecentPosts::class);
}
```

See [Registering a block](registering.md) for more.
