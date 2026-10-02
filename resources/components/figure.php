<?php

/**
 * Figure component (`Component\Layout\Figure`): anything set apart with a
 * caption (the label), such as an image, a table, or a code block.
 *
 *     :::figure[A caption]
 *     ![Describe the photo](photo.jpg)
 *     :::
 *
 * @var Blush\View\Template           $template
 * @var Blush\Component\Layout\Figure  $component
 */

declare(strict_types=1);

?>
<figure <?= $component->attributes() ?>>
	<?= raw($component->content()) ?>
	<?php if ($component->caption() !== '') : ?>
		<figcaption><?= raw($component->caption()) ?></figcaption>
	<?php endif ?>
</figure>
