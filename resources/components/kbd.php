<?php

/**
 * Keyboard input component (`Component\Inline\Kbd`): a key, or a
 * key combination with each key in its own `<kbd>`.
 *
 *     Save with :kbd[Ctrl+S].
 *
 * @var Blush\View\Template         $template
 * @var Blush\Component\Inline\Kbd  $component
 */

declare(strict_types=1);

?>
<kbd <?= $component->attributes() ?>><?php if ($component->isCombination()) : ?><?php foreach ($component->keys as $index => $key) : ?><?= $index > 0 ? '+' : '' ?><kbd><?= e($key) ?></kbd><?php endforeach ?><?php else : ?><?= raw($component->text()) ?><?php endif ?></kbd>
