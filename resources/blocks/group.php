<?php

/**
 * Group block (`Block\Layout\Group`): blocks wrapped in a
 * `<div>`, or a `<section>` or `<aside>` named by the label, so they can
 * be styled together.
 *
 *     :::group{.alignwide}
 *     Some blocks.
 *     :::
 *
 * @var Blush\Block\Layout\Group $block
 */

declare(strict_types=1);

?>
<<?= e($block->tag->value) ?> <?= $block->attributes() ?>>
<?= raw($block->content()) ?>
</<?= e($block->tag->value) ?>>
