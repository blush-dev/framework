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

?>
<nav <?= $component->attributes() ?>>
	<?= $template->include('components/menu/list', component: $component, items: $component->items(), trail: '') ?>
</nav>
