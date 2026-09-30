<?php

/**
 * Callout component (`Component\Callout`): a note set apart from the
 * text, with an optional title (the label). Its variants are `info`,
 * `tip`, `warning`, and `danger`; Default is a plain note.
 *
 *     :::callout[Heads up]{variant=warning}
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
