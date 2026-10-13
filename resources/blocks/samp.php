<?php

/**
 * Sample output block (`Block\Inline\Samp`): what a program prints.
 *
 *     It prints :samp[File not found.]
 *
 * @var Blush\Block\Inline\Samp $block
 */

declare(strict_types=1);

?>
<samp <?= $block->attributes() ?>><?= raw($block->text()) ?></samp>
