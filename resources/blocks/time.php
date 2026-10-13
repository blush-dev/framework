<?php

/**
 * Time block (`Block\Inline\Time`): a date, time, or
 * duration with its machine-readable `datetime`. Without a label, the
 * date is shown in the site's language.
 *
 *     The launch is :time[next Tuesday]{datetime=2026-10-06}.
 *
 * @var Blush\Block\Inline\Time $block
 */

declare(strict_types=1);

?>
<time <?= $block->attributes() ?>><?= raw($block->text()) ?></time>
