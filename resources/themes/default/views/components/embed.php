<?php

/**
 * Embed component (`Component\Embed`): a page from a known provider
 * (YouTube and Vimeo in privacy-friendly frames) at its real aspect
 * ratio, or a link for any other URL. The label is the caption.
 *
 *     ::embed[A caption]{url="https://youtu.be/…" title="Video title"}
 *
 * @var Blush\View\Template  $template
 * @var string               $url
 * @var string               $title
 * @var string               $provider
 * @var string               $providerLabel
 * @var ?string              $src
 * @var ?int                 $width
 * @var ?int                 $height
 * @var ?string              $ratio
 * @var bool                 $portrait
 * @var string               $embedTitle
 * @var string               $slot
 * @var array<string, mixed> $props
 */

declare(strict_types=1);

$caption = is_string($props['label'] ?? null) ? $props['label'] : '';
$name    = match (true) {
	$title !== ''      => $title,
	$embedTitle !== '' => $embedTitle,
	$caption !== ''    => $caption,
	default            => $template->t('embed.title', provider: $providerLabel)
};
$link    = match (true) {
	$title !== ''      => $title,
	$embedTitle !== '' => $embedTitle,
	default            => $url
};

?>
<?php if ($src !== null) : ?>
<figure class="component-embed component-embed--<?= attr($provider) ?><?= $portrait ? ' component-embed--portrait' : '' ?>">
	<div class="component-embed__frame"<?php if ($ratio !== null) : ?> style="--embed-ratio: <?= attr($ratio) ?>"<?php endif ?>>
		<iframe src="<?= url($src) ?>"<?php if ($width !== null && $height !== null) : ?> width="<?= attr((string) $width) ?>" height="<?= attr((string) $height) ?>"<?php endif ?> title="<?= attr($name) ?>" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
	</div>
	<?php if ($slot !== '') : ?>
		<figcaption><?= raw($slot) ?></figcaption>
	<?php endif ?>
</figure>
<?php else : ?>
<p class="component-embed component-embed--link"><a href="<?= url($url) ?>"><?= $slot !== '' ? raw($slot) : e($link) ?></a></p>
<?php endif ?>
