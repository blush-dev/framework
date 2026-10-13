<?php

/**
 * Badge block (`Block\Inline\Badge`): a short label set off from the
 * text. Its variants are `info`, `tip`, `warning`, and `danger`; Default
 * is neutral.
 *
 *     Comments :badge[Beta]{variant=info}
 *
 * @var Blush\Block\Inline\Badge $block
 */

declare(strict_types=1);

?>
<span <?= $block->attributes() ?>><?= raw($block->text()) ?></span>
