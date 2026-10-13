<?php

/**
 * Meter block (`Block\Meter`): a `<meter>` gauge named by
 * its label (the `<label>` around it), with its value as text.
 *
 *     ::meter[Battery]{value=62 low=20 high=80 optimum=100}
 *
 * @var Blush\Block\Meter $block
 */

declare(strict_types=1);

?>
<label <?= $block->attributes() ?>>
	<?php if ($block->label !== '') : ?>
		<span class="<?= attr($block->elementClass('label')) ?>"><?= e($block->label) ?></span>
	<?php endif ?>
	<meter <?= $block->gaugeAttributes() ?>><?= e($block->text()) ?></meter>
	<span class="<?= attr($block->elementClass('value')) ?>"><?= e($block->text()) ?></span>
</label>
