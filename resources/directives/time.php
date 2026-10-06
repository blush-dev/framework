<?php

/**
 * Time directive (`Directive\Inline\Time`): a date, time, or
 * duration with its machine-readable `datetime`. Without a label, the
 * date is shown in the site's language.
 *
 *     The launch is :time[next Tuesday]{datetime=2026-10-06}.
 *
 * @var Blush\View\Template          $template
 * @var Blush\Directive\Inline\Time  $directive
 */

declare(strict_types=1);

?>
<time <?= $directive->attributes() ?>><?= raw($directive->text()) ?></time>
