<?php

/**
 * Icon component (`Component\Icon`): an icon as inline SVG, `1em`
 * square in the text color, labeled or decorative.
 *
 *     :icon[Home]{name=house}
 *     :icon[]{name=heart .loved}
 *
 * @var Blush\View\Template   $template
 * @var Blush\Component\Icon  $component
 */

declare(strict_types=1);

?>
<?= raw($component->markup()) ?>
