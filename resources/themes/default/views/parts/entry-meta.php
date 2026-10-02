<?php

/**
 * An entry's byline: its publish date, the people it credits (D-351),
 * and its terms. The type's first people field is the byline ("By
 * Jane"); any others follow with their own label ("Photographer: Sam").
 * Each person links to their archive under the field, else their
 * profile's page.
 *
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */

declare(strict_types=1);

$published = $entry->type->name === 'page' ? null : $entry->published;
$credits   = [];

foreach (array_values($entry->type->people) as $position => $field) {
	$names = array_map(static function (Blush\Content\Entry\Entry $person) use ($template, $entry, $field): string {
		$link = $template->bylineUrl($person, $entry, $field->field);

		return $link === ''
			? '<span class="entry-meta__person">' . e($person->title) . '</span>'
			: '<a class="entry-meta__person" href="' . url($link) . '">' . e($person->title) . '</a>';
	}, $template->people($entry, $field->field));

	if ($names !== []) {
		$credits[] = [
			$position === 0 ? $template->t('people.byline') : $template->t('people.credit', label: count($names) > 1 ? $field->plural : $field->singular),
			implode(', ', $names)
		];
	}
}

$terms = [];

foreach (array_keys($entry->terms) as $taxonomy) {
	foreach ($template->terms($entry, $taxonomy) as $term) {
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
					<a class="entry-meta__term" href="<?= url($template->permalink($term)) ?>"><?= e($term->title) ?></a>
				<?php endforeach ?>
			</span>
		<?php endif ?>
	</p>
<?php endif ?>
