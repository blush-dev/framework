<?php

/**
 * Grid block (`Block\Layout\Grid`): each block inside is a
 * cell, in up to `columns` columns that wrap on narrow screens. It's a
 * `<div>`, or a `<section>` or `<aside>` named by the label.
 *
 *     :::grid{columns=3}
 *     First.
 *
 *     Second.
 *
 *     Third.
 *     :::
 *
 * @var Blush\Block\Layout\Grid $block
 */

declare(strict_types=1);

?>
<<?= e($block->tag->value) ?> <?= $block->attributes() ?>>
<?= raw($block->content()) ?>
</<?= e($block->tag->value) ?>>
