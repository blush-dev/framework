<?php

/**
 * Row component (`View\Component\Layout\Row`): each block inside is an
 * item, side by side, wrapping when they don't fit.
 *
 *     :::row{justify=between}
 *     [Previous](/one)
 *
 *     [Next](/three)
 *     :::
 *
 * @var Blush\View\Template  $template
 * @var string               $style
 * @var string               $slot
 * @var array<string, mixed> $props
 */

declare(strict_types=1);

$class = trim('component-row ' . (is_string($props['class'] ?? null) ? $props['class'] : ''));
$id    = is_string($props['id'] ?? null) ? $props['id'] : '';

?>
<div class="<?= attr($class) ?>"<?php if ($id !== '') : ?> id="<?= attr($id) ?>"<?php endif ?> style="<?= attr($style) ?>">
<?= raw($slot) ?>
</div>
