<?php

/**
 * Figure directive (`Directive\Layout\Figure`): anything set apart with a
 * caption (the label), such as an image, a table, or a code block.
 *
 *     :::figure[A caption]
 *     ![Describe the photo](photo.jpg)
 *     :::
 *
 * @var Blush\View\Template           $template
 * @var Blush\Directive\Layout\Figure  $directive
 */

declare(strict_types=1);

?>
<figure <?= $directive->attributes() ?>>
	<?= raw($directive->content()) ?>
	<?php if ($directive->caption() !== '') : ?>
		<figcaption><?= raw($directive->caption()) ?></figcaption>
	<?php endif ?>
</figure>
