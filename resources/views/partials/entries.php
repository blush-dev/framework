<?php

/**
 * A page's listed entries, with pagination, when the theme has no
 * `partials/entries` (D-632).
 *
 * @var Blush\View\Template            $template
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

$entries = $page->entries;

?>
<?php if ($entries === null || count($entries) === 0) : ?>
	<p><?= e($template->t('no_entries')) ?></p>
<?php else : ?>
	<?php foreach ($entries as $item) : ?>
		<?= $template->include('partials/entry-summary', entry: $item) ?>
	<?php endforeach ?>

	<?= $template->include('partials/pagination', page: $page) ?>
<?php endif ?>
