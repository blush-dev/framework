<?php

/**
 * Grid component (`Component\Layout\Grid`): each block inside is a
 * cell, in up to `columns` columns that wrap on narrow screens.
 *
 *     :::grid{columns=3}
 *     First.
 *
 *     Second.
 *
 *     Third.
 *     :::
 *
 * @var Blush\View\Template  $template
 * @var string               $style
 * @var string               $slot
 * @var array<string, mixed> $props
 */

declare(strict_types=1);

$class = trim('component-grid ' . (is_string($props['class'] ?? null) ? $props['class'] : ''));
$id    = is_string($props['id'] ?? null) ? $props['id'] : '';

?>
<div class="<?= attr($class) ?>"<?php if ($id !== '') : ?> id="<?= attr($id) ?>"<?php endif ?> style="<?= attr($style) ?>">
<?= raw($slot) ?>
</div>
