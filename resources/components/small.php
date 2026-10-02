<?php

/**
 * Small print component (`Component\Inline\Small`): side comments and small print.
 *
 *     :small[Prices include tax.]
 *
 * @var Blush\View\Template           $template
 * @var Blush\Component\Inline\Small  $component
 */

declare(strict_types=1);

?>
<small <?= $component->attributes() ?>><?= raw($component->text()) ?></small>
