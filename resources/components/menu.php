<?php

/**
 * Menu component (`Component\Menu`): the menu a theme location shows, in
 * a `<nav>` named by its label.
 *
 *     <?= $template->component('menu', name: 'primary') ?>
 *
 * @var Blush\View\Template   $template
 * @var Blush\Component\Menu  $component
 */

declare(strict_types=1);

/**
 * Prints a level of the list, and itself for each submenu. `$trail` is
 * the parent item's position (`2-1`), or `''` at the top.
 *
 * @param list<Blush\Menu\MenuItem> $items
 */
$list = static function (array $items, string $trail) use (&$list, $template, $component): void { ?>
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
					<?php $list($item->children, $position) ?>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ul>
<?php };

?>
<nav <?= $component->attributes() ?>>
	<?php $list($component->items(), '') ?>
</nav>
