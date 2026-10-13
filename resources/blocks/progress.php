<?php

/**
 * Progress block (`Block\Progress`): a `<progress>` bar
 * named by its label (the `<label>` around it), with its value as text.
 * Without a value, the bar is indeterminate.
 *
 *     ::progress[Reading challenge]{value=12 max=50}
 *
 * @var Blush\Block\Progress $block
 */

declare(strict_types=1);

?>
<label <?= $block->attributes() ?>>
	<?php if ($block->label !== '') : ?>
		<span class="<?= attr($block->elementClass('label')) ?>"><?= e($block->label) ?></span>
	<?php endif ?>
	<progress <?= $block->barAttributes() ?>><?= e($block->text()) ?></progress>
	<?php if ($block->text() !== '') : ?>
		<span class="<?= attr($block->elementClass('value')) ?>"><?= e($block->text()) ?></span>
	<?php endif ?>
</label>
