<?php

/**
 * Figure component (`Component\Media\Figure`): an image with a caption
 * (the label).
 *
 *     ::figure[A caption]{src=photo.jpg alt="Describe the photo"}
 *
 * @var Blush\View\Template           $template
 * @var Blush\Component\Media\Figure  $component
 */

declare(strict_types=1);

?>
<figure <?= $component->attributes() ?>>
	<img src="<?= url($component->src) ?>" alt="<?= attr($component->alt) ?>" loading="lazy">
	<?php if ($component->caption() !== '') : ?>
		<figcaption><?= raw($component->caption()) ?></figcaption>
	<?php endif ?>
</figure>
