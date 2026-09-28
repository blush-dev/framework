<?php

/**
 * Figure component: an image with a caption (the label).
 *
 *     ::figure[A caption]{src="/media/photo.jpg" alt="Describe the photo"}
 *
 * @var Blush\View\Template $template
 * @var string              $slot
 * @var array<string, mixed> $props
 */

declare(strict_types=1);

$src   = is_string($props['src'] ?? null) ? $props['src'] : '';
$alt   = is_string($props['alt'] ?? null) ? $props['alt'] : '';
$class = trim('component-figure ' . (is_string($props['class'] ?? null) ? $props['class'] : ''));

?>
<?php if ($src !== '') : ?>
<figure class="<?= attr($class) ?>">
	<img src="<?= url($src) ?>" alt="<?= attr($alt) ?>" loading="lazy">
	<?php if ($slot !== '') : ?>
		<figcaption><?= raw($slot) ?></figcaption>
	<?php endif ?>
</figure>
<?php endif ?>
