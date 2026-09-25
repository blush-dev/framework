<?php

/**
 * A page's listed entries, with pagination.
 *
 * @var Blush\View\Template             $this
 * @var Blush\Content\Http\ContentPage  $page
 */

declare(strict_types=1);

$entries = $page->entries;

?>
<?php if ($entries === null || count($entries) === 0) : ?>
	<p class="no-entries"><?= e($this->t('no_entries')) ?></p>
<?php else : ?>
	<ul class="entries" role="list">
		<?php foreach ($entries as $item) : ?>
			<li class="entries__item"><?= $this->insert('parts/entry-summary', entry: $item) ?></li>
		<?php endforeach ?>
	</ul>

	<?= $this->insert('parts/pagination', page: $page) ?>
<?php endif ?>
