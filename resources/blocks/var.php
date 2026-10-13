<?php

/**
 * Variable block (`Block\Inline\Variable`): a variable in math or code.
 *
 *     Solve for :var[x].
 *
 * @var Blush\Block\Inline\Variable $block
 */

declare(strict_types=1);

?>
<var <?= $block->attributes() ?>><?= raw($block->text()) ?></var>
