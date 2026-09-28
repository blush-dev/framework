<?php

/**
 * Keyboard input component (`View\Component\Inline\Kbd`): a key, or a
 * key combination with each key in its own `<kbd>`.
 *
 *     Save with :kbd[Ctrl+S].
 *
 * @var Blush\View\Template $template
 * @var list<string>        $keys
 * @var string              $slot
 */

declare(strict_types=1);

?>
<?php if (count($keys) > 1) : ?>
<kbd class="component-kbd"><?= implode('+', array_map(static fn (string $key): string => '<kbd>' . e($key) . '</kbd>', $keys)) ?></kbd>
<?php elseif ($keys !== []) : ?>
<kbd class="component-kbd"><?= e($keys[0]) ?></kbd>
<?php else : ?>
<kbd class="component-kbd"><?= raw($slot) ?></kbd>
<?php endif ?>
