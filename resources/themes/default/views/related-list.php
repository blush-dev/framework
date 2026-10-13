<?php

/**
 * What a type's relation with an archive word links to (D-596, D-602),
 * such as a blog's authors or a movie library's directors, each linking
 * to their archive under the relation. The list's own page (`_authors`
 * in the type's folder), when it has one, gives the title and introduces
 * the list.
 *
 * @var Blush\View\Template            $template
 * @var Blush\Translation\Messages     $messages
 * @var Blush\Content\Http\ContentPage $page
 * @var ?Blush\Content\Entry\Entry     $entry
 * @var Blush\Content\Type\ContentType $type
 * @var ?Blush\Content\Query\Paginator $entries
 * @var string                         $title
 */

declare(strict_types=1);

use Blush\Support\Bem;

$people        = new Bem('people');
$archiveHeader = new Bem('archive-header');

$template->layout('base');

$relation = $page->relation->name ?? '';

?>
<header class="<?= attr($archiveHeader->block()) ?>">
	<h1 class="<?= attr($archiveHeader->element('title')) ?>"><?= e($title) ?></h1>

	<?php if ($entry !== null && $entry->raw() !== '') : ?>
		<div class="<?= attr($archiveHeader->element('description')) ?>">
			<?= raw($entry->content()) ?>
		</div>
	<?php endif ?>
</header>

<?php if ($entries === null || count($entries) === 0) : ?>
	<p class="no-entries"><?= e($messages->t('people.none')) ?></p>
<?php else : ?>
	<ul class="<?= attr($people->block()) ?>" role="list">
		<?php foreach ($entries as $person) : ?>
			<li class="<?= attr($people->element('item')) ?>">
				<h2 class="<?= attr($people->element('name')) ?>"><a href="<?= url($person->archiveUrl($type, $relation)) ?>"><?= e($person->title) ?></a></h2>

				<?php if ($person->raw() !== '') : ?>
					<div class="<?= attr($people->element('bio')) ?>">
						<?= raw($person->excerpt()) ?>
					</div>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ul>
<?php endif ?>
