<?php

/**
 * Callout component: a note set apart from the text, with an optional
 * title (the label) and a tone: `note`, `info`, `tip`, `warning`, or
 * `danger`.
 *
 *     :::callout[Heads up]{tone=warning}
 *     Back up your site first.
 *     :::
 *
 * @var Blush\View\Template  $this
 * @var string               $slot
 * @var array<string, mixed> $props
 */

declare(strict_types=1);

$tone  = in_array($props['tone'] ?? null, ['note', 'info', 'tip', 'warning', 'danger'], true) ? $props['tone'] : 'note';
$title = is_string($props['label'] ?? null) ? $props['label'] : (is_string($props['title'] ?? null) ? $props['title'] : '');

?>
<aside class="callout callout--<?= attr($tone) ?>" role="note">
	<?php if ($title !== '') : ?>
		<p class="callout__title"><?= e($title) ?></p>
	<?php endif ?>
	<?= raw($slot) ?>
</aside>
