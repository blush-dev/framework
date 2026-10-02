<?php

/**
 * Table of contents component (`Component\Toc`): the entry's
 * headings, nested by level, each linking to its heading. The label is
 * shown as a title and names the navigation.
 *
 *     ::toc[On this page]{max=4}
 *
 * @var Blush\View\Template  $template
 * @var Blush\Component\Toc  $component
 */

declare(strict_types=1);

/**
 * Prints a level of the list, and itself for each heading's subheadings.
 *
 * @param list<Blush\Component\TocItem> $items
 */
$list = static function (array $items) use (&$list): void { ?>
	<ol class="component-toc__list">
		<?php foreach ($items as $item) : ?>
			<li class="component-toc__item">
				<a class="component-toc__link" href="<?= attr($item->href()) ?>"><?= e($item->text) ?></a>
				<?php if ($item->children !== []) : ?>
					<?php $list($item->children) ?>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ol>
<?php };

?>
<nav <?= $component->attributes() ?>>
	<?php if ($component->heading() !== '') : ?>
		<p class="component-toc__title"><?= raw($component->heading()) ?></p>
	<?php endif ?>
	<?php $list($component->items) ?>
</nav>
