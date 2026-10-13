<?php

/**
 * Citation block (`Block\Inline\Cite`): the title of a work.
 *
 *     From :cite[The Hobbit].
 *
 * @var Blush\Block\Inline\Cite $block
 */

declare(strict_types=1);

?>
<cite <?= $block->attributes() ?>><?= raw($block->text()) ?></cite>
