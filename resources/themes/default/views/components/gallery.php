<?php

/**
 * Gallery component: its content (usually images) in a grid.
 *
 *     :::gallery{columns=3}
 *     ![](a.jpg) ![](b.jpg) ![](c.jpg)
 *     :::
 *
 * @var Blush\View\Template $template
 * @var string              $slot
 * @var array<string, mixed> $props
 */

declare(strict_types=1);

$columns = max(1, min(6, (int) ($props['columns'] ?? 3)));
$class   = trim('component-gallery ' . (is_string($props['class'] ?? null) ? $props['class'] : ''));

?>
<div class="<?= attr($class) ?>" style="--gallery-columns: <?= $columns ?>">
<?= raw($slot) ?>
</div>
