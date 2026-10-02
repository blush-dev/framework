<?php

/**
 * Stack component (`Component\Layout\Stack`): each block inside is an
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
 * @var Blush\Component\Layout\Stack   $component
 */

declare(strict_types=1);

?>
<<?= e($component->tag->value) ?> <?= $component->attributes() ?>>
<?= raw($component->content()) ?>
</<?= e($component->tag->value) ?>>
