<?php

/**
 * Callout block (`Block\Callout`): a note set apart from the
 * text, with an optional title (the label). Its variants are `info`,
 * `tip`, `warning`, and `danger`; Default is a plain note.
 *
 *     :::callout[Heads up]{variant=warning}
 *     Back up your site first.
 *     :::
 *
 * @var Blush\Block\Callout $block
 */

declare(strict_types=1);

?>
<aside <?= $block->attributes() ?>>
	<?php if ($block->heading() !== '') : ?>
		<p class="<?= attr($block->elementClass('title')) ?>"><?= raw($block->heading()) ?></p>
	<?php endif ?>
	<?= raw($block->content()) ?>
</aside>
