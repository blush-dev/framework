<?php

/**
 * Icon block (`Block\Icon`): an icon as inline SVG, `1em`
 * square in the text color, labeled or decorative.
 *
 *     :icon[Home]{name=house}
 *     :icon[]{name=heart .loved}
 *
 * @var Blush\Block\Icon $block
 */

declare(strict_types=1);

?>
<?= raw($block->markup()) ?>
