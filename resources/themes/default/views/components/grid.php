<?php

/**
 * Grid component (`Component\Layout\Grid`): each block inside is a
 * cell, in up to `columns` columns that wrap on narrow screens.
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
<div <?= $component->attributes() ?>>
<?= raw($component->content()) ?>
</div>
