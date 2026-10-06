<?php

/**
 * Table of contents directive (`Directive\Toc`): the entry's
 * headings, nested by level, each linking to its heading. The label is
 * shown as a title and names the navigation.
 *
 *     ::toc[On this page]{max=4}
 *
 * @var Blush\View\Template  $template
 * @var Blush\Directive\Toc  $directive
 */

declare(strict_types=1);

/**
 * Prints a level of the list, and itself for each heading's subheadings.
 *
 * @param list<Blush\Directive\TocItem> $items
 */
$list = static function (array $items) use (&$list): void { ?>
	<ol class="directive-toc__list">
		<?php foreach ($items as $item) : ?>
			<li class="directive-toc__item">
				<a class="directive-toc__link" href="<?= attr($item->href()) ?>"><?= e($item->text) ?></a>
				<?php if ($item->children !== []) : ?>
					<?php $list($item->children) ?>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ol>
<?php };

?>
<nav <?= $directive->attributes() ?>>
	<?php if ($directive->heading() !== '') : ?>
		<p class="directive-toc__title"><?= raw($directive->heading()) ?></p>
	<?php endif ?>
	<?php $list($directive->items) ?>
</nav>
