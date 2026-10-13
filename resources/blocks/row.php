<?php

/**
 * Row block (`Block\Layout\Row`): each block inside is an
 * item, side by side, wrapping when they don't fit. It's a `<div>`, or a
 * `<section>` or `<aside>` named by the label.
 *
 *     :::row{justify=between}
 *     [Previous](/one)
 *
 *     [Next](/three)
 *     :::
 *
 * @var Blush\Block\Layout\Row $block
 */

declare(strict_types=1);

?>
<<?= e($block->tag->value) ?> <?= $block->attributes() ?>>
<?= raw($block->content()) ?>
</<?= e($block->tag->value) ?>>
