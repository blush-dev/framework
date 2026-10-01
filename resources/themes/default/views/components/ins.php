<?php

/**
 * Insertion component (`Component\Inline\Ins`): text added after the fact, with
 * `datetime` and `cite` when they're valid.
 *
 *     The plugin is ~~$20~~ :ins[free]{datetime=2026-10-06}.
 *
 * @var Blush\View\Template         $template
 * @var Blush\Component\Inline\Ins  $component
 */

declare(strict_types=1);

?>
<ins <?= $component->attributes() ?>><?= raw($component->text()) ?></ins>
