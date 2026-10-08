<?php

/**
 * One entry in a listing, when the theme has no `partials/entry-summary`
 * (D-632): its linked title, byline, and excerpt.
 *
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */

declare(strict_types=1);

$link  = $template->permalink($entry);
$title = $entry->title !== '' ? $entry->title : $entry->slug;

?>
<article class="entry">
	<h2>
		<?php if ($link !== '') : ?>
			<a href="<?= url($link) ?>"><?= e($title) ?></a>
		<?php else : ?>
			<?= e($title) ?>
		<?php endif ?>
	</h2>

	<?= $template->include('partials/entry-meta', entry: $entry) ?>

	<?= raw($entry->excerpt()) ?>
</article>
