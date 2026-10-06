<?php

/**
 * Gallery component (`Component\Media\Gallery`): its content (usually
 * images) in rows that fill the width (`flex`) or an even grid (`grid`).
 *
 *     :::gallery{columns=3 layout=grid}
 *     ![](a.jpg)
 *     ![](b.jpg)
 *     ![](c.jpg)
 *     :::
 *
 * @var Blush\View\Template            $template
 * @var Blush\Component\Media\Gallery  $component
 */

declare(strict_types=1);

?>
<div <?= $component->attributes() ?>>
<?= raw($component->content()) ?>
</div>
