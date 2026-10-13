<?php

/**
 * Stack block (`Block\Layout\Stack`): each block inside is an
 * item, one above another with an even gap between them. It's a
 * `<div>`, or a `<section>` or `<aside>` named by the label.
 *
 *     :::stack{gap=2rem}
 *     ## Plans
 *
 *     Pick the one that fits.
 *     :::
 *
 * @var Blush\Block\Layout\Stack $block
 */

declare(strict_types=1);

?>
<<?= e($block->tag->value) ?> <?= $block->attributes() ?>>
<?= raw($block->content()) ?>
</<?= e($block->tag->value) ?>>
