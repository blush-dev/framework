<?php

/**
 * An entry's byline: its publish date, the people it credits (D-602),
 * and its terms. The type's byline relation comes first ("By Jane"); any
 * other credits follow with their own label ("Photographer: Sam"). Each
 * person links to their archive under the relation, else their
 * profile's page.
 *
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */

declare(strict_types=1);

$published = $entry->type->name === 'page' ? null : $entry->published;
$credits   = [];

// What an entry leads to comes back whatever its status (D-802), so
// only published people and terms show.
$live = static fn (Blush\Content\Entry\Entry $item): bool => $item->isPublished() && $item->isRoutable();

foreach ($entry->credits() as $credit) {
	$people = array_filter($credit['people'], $live);

	if ($people === []) {
		continue;
	}

	$relation = $credit['relation'];
	$names    = array_map(static function (Blush\Content\Entry\Entry $person) use ($template, $entry, $relation): string {
		$link = $template->archiveUrl($entry->type, $relation->name, $person) ?: $person->url();

		return $link === ''
			? '<span class="entry-meta__person">' . e($person->title) . '</span>'
			: '<a class="entry-meta__person" href="' . url($link) . '">' . e($person->title) . '</a>';
	}, $people);

	$credits[] = [
		$credit['byline'] ? $template->t('people.byline') : $template->t('people.credit', label: count($names) > 1 ? ($relation->label ?: ucfirst($relation->name)) : $relation->singular),
		implode(', ', $names)
	];
}

$terms = [];

foreach (array_keys($entry->terms) as $taxonomy) {
	foreach (array_filter($entry->terms($taxonomy), $live) as $term) {
		$terms[] = $term;
	}
}

?>
<?php if ($published !== null || $credits !== [] || $terms !== []) : ?>
	<p class="entry-meta">
		<?php if ($published !== null) : ?>
			<time datetime="<?= attr($published->format(DATE_ATOM)) ?>"><?= e($template->date($published)) ?></time>
		<?php endif ?>

		<?php foreach ($credits as [$label, $names]) : ?>
			<span class="entry-meta__people"><?= e($label) ?> <?= raw($names) ?></span>
		<?php endforeach ?>

		<?php if ($terms !== []) : ?>
			<span class="entry-meta__terms">
				<span class="screen-reader-text"><?= e($template->t('terms.label')) ?></span>
				<?php foreach ($terms as $term) : ?>
					<a class="entry-meta__term" href="<?= url($term->url()) ?>"><?= e($term->title) ?></a>
				<?php endforeach ?>
			</span>
		<?php endif ?>
	</p>
<?php endif ?>
