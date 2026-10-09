<?php

/**
 * A single entry, when the theme has no `single` view (D-632), with any
 * listing its `collection` front matter asks for.
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
<article class="entry">
	<header>
		<h1><?= e($title !== '' ? $title : $entry->slug) ?></h1>

		<?php if ($entry->subtitle() !== '') : ?>
			<p><?= e($entry->subtitle()) ?></p>
		<?php endif ?>

		<?= $template->include('partials/entry-meta', entry: $entry) ?>
	</header>

	<?= raw($entry->content()) ?>
</article>

<?php if ($entries !== null) : ?>
	<?= $template->include('partials/entries', page: $page) ?>
<?php endif ?>
