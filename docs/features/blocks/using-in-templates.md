# Using blocks in templates

A template can draw any [block](../../concepts/blocks/README.md), as
content would:

```php
<?= $template->block('callout', variant: 'tip')->content('<p>Saved!</p>') ?>
```

Props are named arguments. `->content()` fills the block's content
(in Markdown, the wrapped block or the label). When the content is text,
such as a button's text, you can pass it as `label` instead, and it's
escaped for you:
`$template->block('button', url: '/start', label: 'Start & go')`.

## Blocks a site may not have

A block that isn't registered is an error. For a block from a plugin the
site may not have, use `blockIf()`, which renders nothing when the block
isn't registered, or ask first with `hasBlock()`:

```php
<?= $template->blockIf('acme/share', variant: 'compact') ?>

<?php if ($template->hasBlock('acme/share')) : ?>
	<aside class="post-share"><?= $template->block('acme/share') ?></aside>
<?php endif ?>
```

A name that isn't valid, or a block that's registered but fails, is
still an error.

For pieces of a theme's own design, such as a card or a post header,
use a [component](../components/README.md) instead.
