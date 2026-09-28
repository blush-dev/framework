<?php

/**
 * Row component (`Component\Layout\Row`): each block inside is an
 * item, side by side, wrapping when they don't fit.
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
<div <?= $component->attributes() ?>>
<?= raw($component->content()) ?>
</div>
