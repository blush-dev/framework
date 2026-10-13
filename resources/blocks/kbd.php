<?php

/**
 * Keyboard input block (`Block\Inline\Kbd`): a key, or a
 * key combination with each key in its own `<kbd>`.
 *
 *     Save with :kbd[Ctrl+S].
 *
 * @var Blush\Block\Inline\Kbd $block
 */

declare(strict_types=1);

?>
<kbd <?= $block->attributes() ?>><?php if ($block->isCombination()) : ?><?php foreach ($block->keys as $index => $key) : ?><?= $index > 0 ? '+' : '' ?><kbd><?= e($key) ?></kbd><?php endforeach ?><?php else : ?><?= raw($block->text()) ?><?php endif ?></kbd>
