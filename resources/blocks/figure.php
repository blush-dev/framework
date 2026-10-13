<?php

/**
 * Figure block (`Block\Layout\Figure`): anything set apart with a
 * caption (the label), such as an image, a table, or a code block.
 *
 *     :::figure[A caption]
 *     ![Describe the photo](photo.jpg)
 *     :::
 *
 * @var Blush\Block\Layout\Figure $block
 */

declare(strict_types=1);

?>
<figure <?= $block->attributes() ?>>
	<?= raw($block->content()) ?>
	<?php if ($block->caption() !== '') : ?>
		<figcaption><?= raw($block->caption()) ?></figcaption>
	<?php endif ?>
</figure>
