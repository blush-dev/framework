<?php

/**
 * One entry in a listing: its linked title, byline, and excerpt.
 *
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */

declare(strict_types=1);

$link  = $template->permalink($entry);
$title = $entry->title !== '' ? $entry->title : $entry->slug;

?>
<article class="entry entry--summary">
	<h2 class="entry__title">
		<?php if ($link !== '') : ?>
			<a href="<?= url($link) ?>"><?= e($title) ?></a>
		<?php else : ?>
			<?= e($title) ?>
		<?php endif ?>
	</h2>

	<?= $template->include('parts/entry-meta', entry: $entry) ?>

	<div class="entry__excerpt">
		<?= raw($entry->excerpt()) ?>
	</div>
</article>
