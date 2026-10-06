<?php

/**
 * Icon directive (`Directive\Icon`): an icon as inline SVG, `1em`
 * square in the text color, labeled or decorative.
 *
 *     :icon[Home]{name=house}
 *     :icon[]{name=heart .loved}
 *
 * @var Blush\View\Template   $template
 * @var Blush\Directive\Icon  $directive
 */

declare(strict_types=1);

?>
<?= raw($directive->markup()) ?>
