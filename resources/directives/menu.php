<?php

/**
 * Menu directive (`Directive\Menu`): the menu a theme location shows, or
 * a site menu by name, in a `<nav>` named by its label.
 *
 *     <?= $template->directive('menu', location: 'primary') ?>
 *
 * @var Blush\View\Template   $template
 * @var Blush\Directive\Menu  $directive
 */

declare(strict_types=1);

/**
 * Prints a level of the list, and itself for each submenu. `$trail` is
 * the parent item's position (`2-1`), or `''` at the top.
 *
 * @param list<Blush\Menu\MenuItem> $items
 */
$list = static function (array $items, string $trail) use (&$list, $template, $directive): void { ?>
	<ul <?= $directive->listAttributes($trail) ?>>
		<?php foreach ($items as $index => $item) : ?>
			<?php $position = ltrim("{$trail}-" . ($index + 1), '-') ?>
			<?php $tag = $item->isLink() ? 'a' : 'span' ?>
			<li <?= $directive->itemAttributes($item) ?>>
				<<?= $tag ?> <?= $directive->linkAttributes($item) ?>>
					<?php if ($item->icon !== '') : ?>
						<?= $template->icon($item->icon) ?>
					<?php endif ?>
					<?php if ($item->image !== '') : ?>
						<img class="directive-menu__image" src="<?= url($item->image) ?>" alt="" loading="lazy" />
					<?php endif ?>
					<span class="directive-menu__label"><?= e($item->label) ?></span>
					<?php if ($item->badge !== '') : ?>
						<span class="directive-menu__badge"><?= e($item->badge) ?></span>
					<?php endif ?>
					<?php if ($item->description !== '') : ?>
						<span class="directive-menu__description"><?= e($item->description) ?></span>
					<?php endif ?>
				</<?= $tag ?>>
				<?php if ($item->hasChildren()) : ?>
					<button <?= $directive->toggleAttributes($item, $position) ?>></button>
					<?php $list($item->children, $position) ?>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ul>
<?php };

?>
<nav <?= $directive->attributes() ?>>
	<?php $list($directive->items(), '') ?>
</nav>
