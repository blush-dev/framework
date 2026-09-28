<?php

/**
 * Time component (`View\Component\Inline\Time`): a date, time, or
 * duration with its machine-readable `datetime`. Without a label, the
 * date is shown in the site's language.
 *
 *     The launch is :time[next Tuesday]{datetime=2026-10-06}.
 *
 * @var Blush\View\Template $template
 * @var ?string             $machine
 * @var string              $text
 * @var string              $slot
 */

declare(strict_types=1);

?>
<time class="component-time"<?php if ($machine !== null) : ?> datetime="<?= attr($machine) ?>"<?php endif ?>><?= $slot !== '' ? raw($slot) : e($text) ?></time>
