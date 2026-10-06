<?php

/**
 * Keyboard input directive (`Directive\Inline\Kbd`): a key, or a
 * key combination with each key in its own `<kbd>`.
 *
 *     Save with :kbd[Ctrl+S].
 *
 * @var Blush\View\Template         $template
 * @var Blush\Directive\Inline\Kbd  $directive
 */

declare(strict_types=1);

?>
<kbd <?= $directive->attributes() ?>><?php if ($directive->isCombination()) : ?><?php foreach ($directive->keys as $index => $key) : ?><?= $index > 0 ? '+' : '' ?><kbd><?= e($key) ?></kbd><?php endforeach ?><?php else : ?><?= raw($directive->text()) ?><?php endif ?></kbd>
