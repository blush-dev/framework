<?php

/**
 * Stack directive (`Directive\Layout\Stack`): each block inside is an
 * item, one above another with an even gap between them. It's a
 * `<div>`, or a `<section>` or `<aside>` named by the label.
 *
 *     :::stack{gap=2rem}
 *     ## Plans
 *
 *     Pick the one that fits.
 *     :::
 *
 * @var Blush\View\Template            $template
 * @var Blush\Directive\Layout\Stack   $directive
 */

declare(strict_types=1);

?>
<<?= e($directive->tag->value) ?> <?= $directive->attributes() ?>>
<?= raw($directive->content()) ?>
</<?= e($directive->tag->value) ?>>
