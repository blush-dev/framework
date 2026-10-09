<?php

/**
 * What a type's relation with an archive word links to (D-596, D-602),
 * such as a blog's authors or a movie library's directors, each linking
 * to their archive under the relation. The list's own page (`_authors`
 * in the type's folder), when it has one, gives the title and introduces
 * the list.
 *
 * @var Blush\View\Template             $template
 * @var Blush\Content\Http\ContentPage  $page
 * @var ?Blush\Content\Entry\Entry      $entry
 * @var Blush\Content\Type\ContentType  $type
 * @var ?Blush\Content\Query\Paginator  $entries
 * @var string                          $title
 */

declare(strict_types=1);

$template->layout('base');

$relation = $page->relation->name ?? '';

?>
<header class="archive-header">
	<h1 class="archive-header__title"><?= e($title) ?></h1>

	<?php if ($entry !== null && $entry->raw() !== '') : ?>
		<div class="archive-header__description">
			<?= raw($entry->content()) ?>
		</div>
	<?php endif ?>
</header>

<?php if ($entries === null || count($entries) === 0) : ?>
	<p class="no-entries"><?= e($template->t('people.none')) ?></p>
<?php else : ?>
	<ul class="people" role="list">
		<?php foreach ($entries as $person) : ?>
			<li class="people__item">
				<h2 class="people__name"><a href="<?= url($template->archiveUrl($type, $relation, $person)) ?>"><?= e($person->title) ?></a></h2>

				<?php if ($person->raw() !== '') : ?>
					<div class="people__bio">
						<?= raw($person->excerpt()) ?>
					</div>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ul>
<?php endif ?>
