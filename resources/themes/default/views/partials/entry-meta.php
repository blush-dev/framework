<?php

/**
 * An entry's byline: its publish date, the profiles its byline credits
 * (D-804), and what it's filed under (D-808). What an entry leads to
 * comes back whatever its status (D-802), so only published ones show.
 *
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */

declare(strict_types=1);

$published = $entry->type->name === 'page' ? null : $entry->published;

?>
<?php if ($published !== null || ! $entry->byline()->published()->isEmpty() || ! $entry->classifications()->published()->isEmpty()) : ?>
	<p class="entry-meta">
		<?php if ($published !== null) : ?>
			<time datetime="<?= attr($published->format(DATE_ATOM)) ?>"><?= e($template->date($published)) ?></time>
		<?php endif ?>

		<?php if (! $entry->byline()->published()->isEmpty()) : ?>
			<span class="entry-meta__byline">
				<?= e($template->t('people.byline')) ?>
				<?php foreach ($entry->byline()->published() as $profile) : ?>
					<?= $profile->link(class: 'entry-meta__profile') ?>
				<?php endforeach ?>
			</span>
		<?php endif ?>

		<?php if (! $entry->classifications()->published()->isEmpty()) : ?>
			<span class="entry-meta__terms">
				<span class="screen-reader-text"><?= e($template->t('terms.label')) ?></span>
				<?php foreach ($entry->classifications()->published() as $term) : ?>
					<?= $term->link(class: 'entry-meta__term') ?>
				<?php endforeach ?>
			</span>
		<?php endif ?>
	</p>
<?php endif ?>
