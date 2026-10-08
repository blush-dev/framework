<?php

/**
 * Embed directive (`Directive\Embed`): a page from a known provider
 * (YouTube and Vimeo in privacy-friendly frames) at its real aspect
 * ratio or a player's height, a photo linked to its page with its
 * credit, or a link for any other URL. The label is the caption.
 *
 *     ::embed[A caption]{url="https://youtu.be/…" title="Video title"}
 *     ::embed[A caption]{url="https://www.flickr.com/photos/…" alt="…"}
 *
 * @var Blush\View\Template    $template
 * @var Blush\Directive\Embed  $directive
 */

declare(strict_types=1);

?>
<?php if ($directive->isFramed()) : ?>
<figure <?= $directive->attributes() ?>>
	<div <?= $directive->wrapperAttributes() ?>>
		<iframe <?= $directive->frameAttributes() ?>></iframe>
	</div>
	<?php if ($directive->caption() !== '') : ?>
		<figcaption><?= raw($directive->caption()) ?></figcaption>
	<?php endif ?>
</figure>
<?php elseif ($directive->isPhoto()) : ?>
<figure <?= $directive->attributes() ?>>
	<a href="<?= url($directive->url) ?>"><img <?= $directive->photoAttributes() ?> alt="<?= attr($directive->altText()) ?>"></a>
	<?php if ($directive->caption() !== '' || $directive->credit() !== '') : ?>
		<figcaption><?= raw(implode(' ', array_filter([$directive->caption(), $directive->credit() === '' ? '' : '<span class="directive-embed__credit">' . e($directive->credit()) . '</span>']))) ?></figcaption>
	<?php endif ?>
</figure>
<?php else : ?>
<p <?= $directive->attributes() ?>><a href="<?= url($directive->url) ?>"><?= raw($directive->linkText()) ?></a></p>
<?php endif ?>
