<?php

/**
 * Insertion block (`Block\Inline\Ins`): text added after the fact, with
 * `datetime` and `cite` when they're valid.
 *
 *     The plugin is ~~$20~~ :ins[free]{datetime=2026-10-06}.
 *
 * @var Blush\Block\Inline\Ins $block
 */

declare(strict_types=1);

?>
<ins <?= $block->attributes() ?>><?= raw($block->text()) ?></ins>
