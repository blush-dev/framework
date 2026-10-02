<?php

/**
 * Row component (`Component\Layout\Row`): each block inside is an
 * item, side by side, wrapping when they don't fit. It's a `<div>`, or a
 * `<section>` or `<aside>` named by the label.
 *
 *     :::row{justify=between}
 *     [Previous](/one)
 *
 *     [Next](/three)
 *     :::
 *
 * @var Blush\View\Template         $template
 * @var Blush\Component\Layout\Row  $component
 */

declare(strict_types=1);

?>
<<?= e($component->tag->value) ?> <?= $component->attributes() ?>>
<?= raw($component->content()) ?>
</<?= e($component->tag->value) ?>>
