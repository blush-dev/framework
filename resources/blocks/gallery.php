<?php

/**
 * Gallery block (`Block\Media\Gallery`): its content (usually
 * images) in rows that fill the width (`flex`), an even grid (`grid`),
 * or masonry (`masonry`).
 *
 *     :::gallery{columns=3 layout=grid}
 *     ![](a.jpg)
 *     ![](b.jpg)
 *     ![](c.jpg)
 *     :::
 *
 * @var Blush\Block\Media\Gallery $block
 */

declare(strict_types=1);

?>
<div <?= $block->attributes() ?>>
<?= raw($block->content()) ?>
</div>
