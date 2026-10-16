# Variants

A variant is a named style of a block: `variant=warning` on a
callout, `variant=secondary` on a button. The block decides what the
thing is; the variant decides how it looks.

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::
```

- **Every block has Default,** which is what you get without
  `variant` (or with `variant=default`). It's always there, and no theme
  or plugin can rename or replace it, so content that has never been
  given a variant keeps following the theme.
- **A variant the block doesn't have renders as Default,** such as
  one from a theme that isn't active. `content:lint` warns about these.
- **Variants belong to whoever declares them.** The built-in blocks
  come with a few; a theme or a plugin can add more to
  any block. A theme's apply only while it (or a child of it) is
  active. This is how a theme adds looks to content without adding
  blocks of its own.

The editor in the admin shows a block's variants in a list at the
top of its options, each with its description.

## Styling a variant

A variant adds a class to the block's root element:
`block-{name}--{variant}`, such as `block-callout--warning`. In
a template, `$block->variant` is the variant's name (`'default'` for
none), and `$block->isVariant('warning')` checks it.

When a variant needs more than a class, give it its own template:
`views/blocks/callout-bordered.php` draws a callout with
`variant=bordered`, and the block's own template draws the rest.
(For a plugin's block, that's `{namespace}-{name}-{variant}.php`.)
Variants are meant mostly for looks, but nothing stops one from doing
more.

## Adding variants

A block class lists its own in its `VARIANTS` constant:

```php
final class Tabs extends Block
{
	public const array VARIANTS = ['boxed', 'plain'];
}
```

A theme adds variants to any block in its `theme.json`, by
block:

```json
{
	"name": "acme/notebook",
	"label": "Notebook",
	"namespace": "notebook",
	"variants": {
		"callout": ["bordered", "compact"],
		"tabs/tabs": ["underlined"]
	}
}
```

A plugin's or a theme's provider can add (or remove)
variants from PHP, by listening for `BlockVariantsCollecting`. It
fires once per block, the first time its variants are needed, so the
order providers boot in doesn't matter. The second argument is the
namespace whose catalog has the variant's text:

```php
use Blush\Block\Events\BlockVariantsCollecting;
use Blush\Event\Listener\ListenerRegistry;

$this->container->get(ListenerRegistry::class)->listen(
	BlockVariantsCollecting::class,
	function (BlockVariantsCollecting $event): void {
		if ($event->is('callout')) {
			$event->add('bordered', 'acme');
		}
	}
);
```

A variant's name is lowercase letters, digits, and hyphens, starting with
a letter, and it can't be `default`. To add a class other than
`--{variant}`, give it a modifier: `{"name": "compact", "modifier":
"tight"}` in `theme.json`, or `$event->add('compact', 'acme', 'tight')`.

Each variant's label and description are translatable, in the catalog
of whoever added it (see [Labels and translations](labels-and-translations.md)).
