<?php

/**
 * Embed block (`Block\Embed`): a page from a known provider
 * (YouTube and Vimeo in privacy-friendly frames) at its real aspect
 * ratio or a player's height, a photo linked to its page with its
 * credit, a post as its provider's quote (X's, which its script
 * draws), or a link for any other URL. The label is the caption.
 *
 *     ::embed[A caption]{url="https://youtu.be/…" title="Video title"}
 *     ::embed[A caption]{url="https://www.flickr.com/photos/…" alt="…"}
 *     ::embed{url="https://x.com/…/status/…"}
 *
 * @var Blush\Block\Embed $block
 */

declare(strict_types=1);

?>
<?php if ($block->isFramed()) : ?>
<figure <?= $block->attributes() ?>>
	<div <?= $block->wrapperAttributes() ?>>
		<iframe <?= $block->frameAttributes() ?>></iframe>
	</div>
	<?php if ($block->caption() !== '') : ?>
		<figcaption><?= raw($block->caption()) ?></figcaption>
	<?php endif ?>
</figure>
<?php elseif ($block->isPhoto()) : ?>
<figure <?= $block->attributes() ?>>
	<a href="<?= url($block->url) ?>"><img <?= $block->photoAttributes() ?> alt="<?= attr($block->altText()) ?>"></a>
	<?php if ($block->caption() !== '' || $block->credit() !== '') : ?>
		<figcaption><?= raw(implode(' ', array_filter([$block->caption(), $block->credit() === '' ? '' : '<span class="' . attr($block->elementClass('credit')) . '">' . e($block->credit()) . '</span>']))) ?></figcaption>
	<?php endif ?>
</figure>
<?php elseif ($block->isRich()) : ?>
<figure <?= $block->attributes() ?>>
	<?= raw($block->quote) ?>

	<?php if ($block->caption() !== '') : ?>
		<figcaption><?= raw($block->caption()) ?></figcaption>
	<?php endif ?>
</figure>
<?php else : ?>
<p <?= $block->attributes() ?>><a href="<?= url($block->url) ?>"><?= raw($block->linkText()) ?></a></p>
<?php endif ?>
