<?php

/**
 * An entry's byline: its publish date, its authors (each linking to
 * their archive in the entry's type, when it has them), and its terms.
 *
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */

declare(strict_types=1);

$published = $entry->type->name === 'page' ? null : $entry->published;
$authors   = array_map(static function (Blush\Content\Entry\Entry $author) use ($template, $entry): string {
	$link = $template->authorUrl($author, $entry);

	return $link === ''
		? '<span class="entry-meta__author">' . e($author->title) . '</span>'
		: '<a class="entry-meta__author" href="' . url($link) . '">' . e($author->title) . '</a>';
}, $template->authors($entry));
$terms     = [];

foreach (array_keys($entry->terms) as $taxonomy) {
	foreach ($template->terms($entry, $taxonomy) as $term) {
		$terms[] = $term;
	}
}

?>
<?php if ($published !== null || $authors !== [] || $terms !== []) : ?>
	<p class="entry-meta">
		<?php if ($published !== null) : ?>
			<time datetime="<?= attr($published->format(DATE_ATOM)) ?>"><?= e($template->date($published)) ?></time>
		<?php endif ?>

		<?php if ($authors !== []) : ?>
			<span class="entry-meta__authors"><?= e($template->t('authors.byline')) ?> <?= raw(implode(', ', $authors)) ?></span>
		<?php endif ?>

		<?php if ($terms !== []) : ?>
			<span class="entry-meta__terms">
				<span class="screen-reader-text"><?= e($template->t('terms.label')) ?></span>
				<?php foreach ($terms as $term) : ?>
					<a class="entry-meta__term" href="<?= url($template->permalink($term)) ?>"><?= e($term->title) ?></a>
				<?php endforeach ?>
			</span>
		<?php endif ?>
	</p>
<?php endif ?>
