<?php

/**
 * Small print block (`Block\Inline\Small`): side comments and small print.
 *
 *     :small[Prices include tax.]
 *
 * @var Blush\Block\Inline\Small $block
 */

declare(strict_types=1);

?>
<small <?= $block->attributes() ?>><?= raw($block->text()) ?></small>
