<?php

/**
 * A single entry, with any listing its `collection` front matter asks
 * for.
 *
 * @var Blush\View\Template            $template
 * @var Blush\Content\Http\ContentPage $page
 * @var Blush\Content\Entry\Entry      $entry
 * @var ?Blush\Content\Query\Paginator $entries
 * @var string                         $title
 */

declare(strict_types=1);

$template->layout('base');

?>
<article class="entry entry--single">
	<header class="entry__header">
		<h1 class="entry__title"><?= e($title !== '' ? $title : $entry->slug) ?></h1>

		<?php if ($entry->subtitle() !== '') : ?>
			<p class="entry__subtitle"><?= e($entry->subtitle()) ?></p>
		<?php endif ?>

		<?= $template->include('parts/entry-meta', entry: $entry) ?>
	</header>

	<div class="entry__content">
		<?= raw($entry->body()) ?>
	</div>
</article>

<?php if ($entries !== null) : ?>
	<?= $template->include('parts/entries', page: $page) ?>
<?php endif ?>
