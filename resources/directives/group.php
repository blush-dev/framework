<?php

/**
 * Group directive (`Directive\Layout\Group`): blocks wrapped in a
 * `<div>`, or a `<section>` or `<aside>` named by the label, so they can
 * be styled together.
 *
 *     :::group{.alignwide}
 *     Some blocks.
 *     :::
 *
 * @var Blush\View\Template           $template
 * @var Blush\Directive\Layout\Group  $directive
 */

declare(strict_types=1);

?>
<<?= e($directive->tag->value) ?> <?= $directive->attributes() ?>>
<?= raw($directive->content()) ?>
</<?= e($directive->tag->value) ?>>
