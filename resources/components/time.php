<?php

/**
 * Time component (`Component\Inline\Time`): a date, time, or
 * duration with its machine-readable `datetime`. Without a label, the
 * date is shown in the site's language.
 *
 *     The launch is :time[next Tuesday]{datetime=2026-10-06}.
 *
 * @var Blush\View\Template          $template
 * @var Blush\Component\Inline\Time  $component
 */

declare(strict_types=1);

?>
<time <?= $component->attributes() ?>><?= raw($component->text()) ?></time>
