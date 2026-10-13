<?php

/**
 * Table of contents block (`Block\Toc`): the entry's
 * headings, nested by level, each linking to its heading. The label is
 * shown as a title and names the navigation.
 *
 *     ::toc[On this page]{max=4}
 *
 * @var Blush\Block\Toc $block
 */

declare(strict_types=1);

/**
 * Prints a level of the list, and itself for each heading's subheadings.
 *
 * @param list<Blush\Block\TocItem> $items
 */
$list = static function (array $items) use (&$list, $block): void { ?>
	<ol class="<?= attr($block->elementClass('list')) ?>">
		<?php foreach ($items as $item) : ?>
			<li class="<?= attr($block->elementClass('item')) ?>">
				<a class="<?= attr($block->elementClass('link')) ?>" href="<?= attr($item->href()) ?>"><?= e($item->text) ?></a>
				<?php if ($item->children !== []) : ?>
					<?php $list($item->children) ?>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ol>
<?php };

?>
<nav <?= $block->attributes() ?>>
	<?php if ($block->heading() !== '') : ?>
		<p class="<?= attr($block->elementClass('title')) ?>"><?= raw($block->heading()) ?></p>
	<?php endif ?>
	<?php $list($block->items) ?>
</nav>
