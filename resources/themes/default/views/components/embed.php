<?php

/**
 * Embed component (`Component\Embed`): a page from a known provider
 * (YouTube and Vimeo in privacy-friendly frames) at its real aspect
 * ratio, or a link for any other URL. The label is the caption.
 *
 *     ::embed[A caption]{url="https://youtu.be/…" title="Video title"}
 *
 * @var Blush\View\Template    $template
 * @var Blush\Component\Embed  $component
 */

declare(strict_types=1);

?>
<?php if ($component->isFramed()) : ?>
<figure <?= $component->attributes() ?>>
	<div <?= $component->wrapperAttributes() ?>>
		<iframe <?= $component->frameAttributes() ?>></iframe>
	</div>
	<?php if ($component->caption() !== '') : ?>
		<figcaption><?= raw($component->caption()) ?></figcaption>
	<?php endif ?>
</figure>
<?php else : ?>
<p <?= $component->attributes() ?>><a href="<?= url($component->url) ?>"><?= raw($component->linkText()) ?></a></p>
<?php endif ?>
