<?php

/**
 * Insertion directive (`Directive\Inline\Ins`): text added after the fact, with
 * `datetime` and `cite` when they're valid.
 *
 *     The plugin is ~~$20~~ :ins[free]{datetime=2026-10-06}.
 *
 * @var Blush\View\Template         $template
 * @var Blush\Directive\Inline\Ins  $directive
 */

declare(strict_types=1);

?>
<ins <?= $directive->attributes() ?>><?= raw($directive->text()) ?></ins>
