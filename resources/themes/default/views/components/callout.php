<?php

/**
 * Callout component (`Component\Callout`): a note set apart from the
 * text, with an optional title (the label) and a tone: `note`, `info`,
 * `tip`, `warning`, or `danger`.
 *
 *     :::callout[Heads up]{tone=warning}
 *     Back up your site first.
 *     :::
 *
 * @var Blush\View\Template      $template
 * @var Blush\Component\Callout  $component
 */

declare(strict_types=1);

?>
<aside <?= $component->attributes() ?>>
	<?php if ($component->heading() !== '') : ?>
		<p class="component-callout__title"><?= raw($component->heading()) ?></p>
	<?php endif ?>
	<?= raw($component->content()) ?>
</aside>
