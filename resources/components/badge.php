<?php

/**
 * Badge component (`Component\Inline\Badge`): a short label set off from the
 * text. Its variants are `info`, `tip`, `warning`, and `danger`; Default
 * is neutral.
 *
 *     Comments :badge[Beta]{variant=info}
 *
 * @var Blush\View\Template           $template
 * @var Blush\Component\Inline\Badge  $component
 */

declare(strict_types=1);

?>
<span <?= $component->attributes() ?>><?= raw($component->text()) ?></span>
