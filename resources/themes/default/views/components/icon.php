<?php

/**
 * Icon component (`Component\Icon`): an icon as inline SVG, `1em`
 * square in the text color, labeled or decorative.
 *
 *     :icon[Home]{name=house}
 *     :icon[]{name=heart .loved}
 *
 * @var Blush\View\Template            $template
 * @var Blush\Component\Icon      $component
 * @var array<string, mixed>           $props
 */

declare(strict_types=1);

?>
<?= raw($component->markup(is_string($props['class'] ?? null) ? $props['class'] : '')) ?>
