<?php

/**
 * Badge directive (`Directive\Inline\Badge`): a short label set off from the
 * text. Its variants are `info`, `tip`, `warning`, and `danger`; Default
 * is neutral.
 *
 *     Comments :badge[Beta]{variant=info}
 *
 * @var Blush\View\Template           $template
 * @var Blush\Directive\Inline\Badge  $directive
 */

declare(strict_types=1);

?>
<span <?= $directive->attributes() ?>><?= raw($directive->text()) ?></span>
