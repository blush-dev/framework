<?php

/**
 * Grid component (`Component\Layout\Grid`): each block inside is a
 * cell, in up to `columns` columns that wrap on narrow screens. It's a
 * `<div>`, or a `<section>` or `<aside>` named by the label.
 *
 *     :::grid{columns=3}
 *     First.
 *
 *     Second.
 *
 *     Third.
 *     :::
 *
 * @var Blush\View\Template          $template
 * @var Blush\Component\Layout\Grid  $component
 */

declare(strict_types=1);

?>
<<?= e($component->tag->value) ?> <?= $component->attributes() ?>>
<?= raw($component->content()) ?>
</<?= e($component->tag->value) ?>>
