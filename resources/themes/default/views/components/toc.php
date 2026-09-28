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

?>
<nav <?= $component->attributes() ?>>
	<?php if ($component->heading() !== '') : ?>
		<p class="component-toc__title"><?= raw($component->heading()) ?></p>
	<?php endif ?>
	<?= $template->include('components/toc/list', items: $component->items) ?>
</nav>
