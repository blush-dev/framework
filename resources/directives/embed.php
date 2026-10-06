<?php

/**
 * Embed directive (`Directive\Embed`): a page from a known provider
 * (YouTube and Vimeo in privacy-friendly frames) at its real aspect
 * ratio, or a link for any other URL. The label is the caption.
 *
 *     ::embed[A caption]{url="https://youtu.be/…" title="Video title"}
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
<?php else : ?>
<p <?= $directive->attributes() ?>><a href="<?= url($directive->url) ?>"><?= raw($directive->linkText()) ?></a></p>
<?php endif ?>
