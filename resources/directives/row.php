<?php

/**
 * Row directive (`Directive\Layout\Row`): each block inside is an
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
 * @var Blush\Directive\Layout\Row  $directive
 */

declare(strict_types=1);

?>
<<?= e($directive->tag->value) ?> <?= $directive->attributes() ?>>
<?= raw($directive->content()) ?>
</<?= e($directive->tag->value) ?>>
