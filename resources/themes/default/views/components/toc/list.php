<?php

/**
 * A table of contents' list (`components/toc`), which includes itself
 * for nested headings.
 *
 * @var Blush\View\Template             $template
 * @var list<Blush\Component\TocItem>   $items
 */

declare(strict_types=1);

?>
<ol class="component-toc__list">
	<?php foreach ($items as $item) : ?>
		<li class="component-toc__item">
			<a class="component-toc__link" href="<?= attr($item->href()) ?>"><?= e($item->text) ?></a>
			<?php if ($item->children !== []) : ?>
				<?= $template->include('components/toc/list', items: $item->children) ?>
			<?php endif ?>
		</li>
	<?php endforeach ?>
</ol>
