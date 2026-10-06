<?php

/**
 * Grid directive (`Directive\Layout\Grid`): each block inside is a
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
 * @var Blush\Directive\Layout\Grid  $directive
 */

declare(strict_types=1);

?>
<<?= e($directive->tag->value) ?> <?= $directive->attributes() ?>>
<?= raw($directive->content()) ?>
</<?= e($directive->tag->value) ?>>
