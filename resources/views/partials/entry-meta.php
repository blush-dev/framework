<?php

/**
 * An entry's byline, when the theme has no `partials/entry-meta`
 * (D-632): its publish date, the profiles it credits (D-602), and what
 * it's filed under (D-808). Each profile links to its archive under the
 * relation, else its own page.
 *
 * @var Blush\View\Template        $template
 * @var Blush\Translation\Messages $messages
 * @var Blush\Content\Entry\Entry  $entry
 */

declare(strict_types=1);

$published = $entry->type->name === 'page' ? null : $entry->published;
$credits   = [];

// What an entry leads to comes back whatever its status (D-802), so
// only published profiles and terms show.
foreach ($entry->relations(Blush\Content\Relation\RelationKind::Credit) as $relation) {
	$profiles = $entry->related($relation->name)->published();

	if ($profiles->isEmpty()) {
		continue;
	}

	$names = array_map(static function (Blush\Content\Entry\Entry $profile) use ($entry, $relation): string {
		$link = $profile->archiveUrl($entry->type, $relation->name) ?: $profile->url();

		return $link === '' ? e($profile->title) : '<a href="' . url($link) . '">' . e($profile->title) . '</a>';
	}, $profiles->all());

	$credits[] = [
		$profiles->byline ? $messages->t('people.byline') : $messages->t('people.credit', label: count($names) > 1 ? ($relation->label ?: ucfirst($relation->name)) : $relation->singular),
		implode(', ', $names)
	];
}

$terms = array_map(static fn (Blush\Content\Entry\Entry $term): string => (string) $term->link(), $entry->classifications()->published()->all());

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
			<span><?= e($messages->t('terms.label')) ?>: <?= raw(implode(', ', $terms)) ?></span>
		<?php endif ?>
	</p>
<?php endif ?>
