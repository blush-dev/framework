<?php

/**
 * Group component (`Component\Layout\Group`): blocks wrapped in a
 * `<div>`, or a `<section>` named by the label, so they can be styled
 * together.
 *
 *     :::group{.alignwide}
 *     Some blocks.
 *     :::
 *
 * @var Blush\View\Template                   $template
 * @var Blush\Component\Layout\GroupTag $tag
 * @var string                                $label
 * @var string                                $slot
 * @var array<string, mixed>                  $props
 */

declare(strict_types=1);

use Blush\Component\Layout\GroupTag;

$class = trim('component-group ' . (is_string($props['class'] ?? null) ? $props['class'] : ''));
$id    = is_string($props['id'] ?? null) ? $props['id'] : '';
$name  = $tag === GroupTag::Section ? $label : '';

?>
<<?= e($tag->value) ?> class="<?= attr($class) ?>"<?php if ($id !== '') : ?> id="<?= attr($id) ?>"<?php endif ?><?php if ($name !== '') : ?> aria-label="<?= attr($name) ?>"<?php endif ?>>
<?= raw($slot) ?>
</<?= e($tag->value) ?>>
