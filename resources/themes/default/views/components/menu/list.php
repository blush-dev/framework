<?php

/**
 * A menu's list (`components/menu`), which includes itself for
 * submenus. `$trail` is the parent item's position (`2-1`), or `''` at
 * the top.
 *
 * @var Blush\View\Template          $template
 * @var Blush\Component\Menu         $component
 * @var list<Blush\Menu\MenuItem>    $items
 * @var string                       $trail
 */

declare(strict_types=1);

?>
<ul <?= $component->listAttributes($trail) ?>>
	<?php foreach ($items as $index => $item) : ?>
		<?php $position = ltrim("{$trail}-" . ($index + 1), '-') ?>
		<?php $tag = $item->isLink() ? 'a' : 'span' ?>
		<li <?= $component->itemAttributes($item) ?>>
			<<?= $tag ?> <?= $component->linkAttributes($item) ?>>
				<?php if ($item->icon !== '') : ?>
					<?= $template->icon($item->icon) ?>
				<?php endif ?>
				<?php if ($item->image !== '') : ?>
					<img class="component-menu__image" src="<?= url($item->image) ?>" alt="" loading="lazy" />
				<?php endif ?>
				<span class="component-menu__label"><?= e($item->label) ?></span>
				<?php if ($item->badge !== '') : ?>
					<span class="component-menu__badge"><?= e($item->badge) ?></span>
				<?php endif ?>
				<?php if ($item->description !== '') : ?>
					<span class="component-menu__description"><?= e($item->description) ?></span>
				<?php endif ?>
			</<?= $tag ?>>
			<?php if ($item->hasChildren()) : ?>
				<button <?= $component->toggleAttributes($item, $position) ?>></button>
				<?= $template->include('components/menu/list', component: $component, items: $item->children, trail: $position) ?>
			<?php endif ?>
		</li>
	<?php endforeach ?>
</ul>
