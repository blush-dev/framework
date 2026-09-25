<?php

/**
 * Embed component (`View\Component\Embed`): a YouTube or Vimeo video in a
 * privacy-friendly frame, or a link for any other URL. The label is the
 * caption.
 *
 *     ::embed[A caption]{url="https://youtu.be/…" title="Video title"}
 *
 * @var Blush\View\Template $this
 * @var string              $url
 * @var string              $title
 * @var string              $provider
 * @var ?string             $src
 * @var string              $slot
 */

declare(strict_types=1);

$label = $title !== '' ? $title : $url;

?>
<?php if ($src !== null) : ?>
<figure class="embed embed--<?= attr($provider) ?>">
	<div class="embed__frame">
		<iframe src="<?= url($src) ?>" title="<?= attr($label) ?>" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
	</div>
	<?php if ($slot !== '') : ?>
		<figcaption><?= raw($slot) ?></figcaption>
	<?php endif ?>
</figure>
<?php else : ?>
<p class="embed embed--link"><a href="<?= url($url) ?>"><?= $slot !== '' ? raw($slot) : e($label) ?></a></p>
<?php endif ?>
