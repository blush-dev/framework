<?php

/**
 * File block (`Block\Media\File`): a download link with the
 * file's format and size. The label is the link text; without one, it's
 * the file's name.
 *
 *     ::file[The annual report]{src=report.pdf}
 *
 * @var Blush\Block\Media\File $block
 */

declare(strict_types=1);

?>
<p <?= $block->attributes() ?>>
	<a class="<?= attr($block->elementClass('link')) ?>" href="<?= url($block->src) ?>" download><?= raw($block->text()) ?></a>
	<?php if ($block->details() !== '') : ?>
		<span class="<?= attr($block->elementClass('details')) ?>">(<?= e($block->details()) ?>)</span>
	<?php endif ?>
</p>
