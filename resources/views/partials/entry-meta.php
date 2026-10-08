<?php

/**
 * An entry's byline, when the theme has no `partials/entry-meta`
 * (D-632): its publish date, the people it credits (D-602), and its
 * terms. Each person links to their archive under the relation, else
 * their profile's page.
 *
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */

declare(strict_types=1);

$published = $entry->type->name === 'page' ? null : $entry->published;
$credits   = [];

foreach ($template->credits($entry) as $credit) {
	$relation = $credit['relation'];
	$names    = array_map(static function (Blush\Content\Entry\Entry $person) use ($template, $entry, $relation): string {
		$link = $template->archiveUrl($entry->type, $relation->name, $person) ?: $template->permalink($person);

		return $link === '' ? e($person->title) : '<a href="' . url($link) . '">' . e($person->title) . '</a>';
	}, $credit['people']);

	$credits[] = [
		$credit['byline'] ? $template->t('people.byline') : $template->t('people.credit', label: count($names) > 1 ? ($relation->label ?: ucfirst($relation->name)) : $relation->singular),
		implode(', ', $names)
	];
}

$terms = [];

foreach (array_keys($entry->terms) as $taxonomy) {
	foreach ($template->terms($entry, $taxonomy) as $term) {
		$terms[] = '<a href="' . url($template->permalink($term)) . '">' . e($term->title) . '</a>';
	}
}

?>
<?php if ($published !== null || $credits !== [] || $terms !== []) : ?>
	<p>
		<?php if ($published !== null) : ?>
			<time datetime="<?= attr($published->format(DATE_ATOM)) ?>"><?= e($template->date($published)) ?></time>
		<?php endif ?>

		<?php foreach ($credits as [$label, $names]) : ?>
			<span><?= e($label) ?> <?= raw($names) ?></span>
		<?php endforeach ?>

		<?php if ($terms !== []) : ?>
			<span><?= e($template->t('terms.label')) ?>: <?= raw(implode(', ', $terms)) ?></span>
		<?php endif ?>
	</p>
<?php endif ?>
