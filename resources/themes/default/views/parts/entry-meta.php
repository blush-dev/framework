<?php

/**
 * An entry's byline: its publish date and its terms.
 *
 * @var Blush\View\Template        $this
 * @var Blush\Content\Entry\Entry  $entry
 */

declare(strict_types=1);

$published = $entry->type->name === 'page' ? null : $entry->published;
$terms     = [];

foreach (array_keys($entry->terms) as $taxonomy) {
	foreach ($this->terms($entry, $taxonomy) as $term) {
		$terms[] = $term;
	}
}

?>
<?php if ($published !== null || $terms !== []) : ?>
	<p class="entry-meta">
		<?php if ($published !== null) : ?>
			<time datetime="<?= attr($published->format(DATE_ATOM)) ?>"><?= e($this->date($published)) ?></time>
		<?php endif ?>

		<?php if ($terms !== []) : ?>
			<span class="entry-meta__terms">
				<span class="screen-reader-text"><?= e($this->t('terms.label')) ?></span>
				<?php foreach ($terms as $term) : ?>
					<a class="entry-meta__term" href="<?= url($this->permalink($term)) ?>"><?= e($term->title) ?></a>
				<?php endforeach ?>
			</span>
		<?php endif ?>
	</p>
<?php endif ?>
