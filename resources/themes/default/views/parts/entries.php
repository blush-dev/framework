<?php

/**
 * A page's listed entries, with pagination.
 *
 * @var Blush\View\Template            $template
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

$entries = $page->entries;

?>
<?php if ($entries === null || count($entries) === 0) : ?>
	<p class="no-entries"><?= e($template->t('no_entries')) ?></p>
<?php else : ?>
	<ul class="entries" role="list">
		<?php foreach ($entries as $item) : ?>
			<li class="entries__item"><?= $template->include('parts/entry-summary', entry: $item) ?></li>
		<?php endforeach ?>
	</ul>

	<?= $template->include('parts/pagination', page: $page) ?>
<?php endif ?>
