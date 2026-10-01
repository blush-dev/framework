<?php

/**
 * A type's authors: the people its entries credit, each linking to their
 * archive in the type. The type's authors page (`_authors` in its
 * folder), when it has one, gives the title and introduces the list.
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

?>
<header class="archive-header">
	<h1 class="archive-header__title"><?= e($title) ?></h1>

	<?php if ($entry !== null && $entry->raw() !== '') : ?>
		<div class="archive-header__description">
			<?= raw($entry->body()) ?>
		</div>
	<?php endif ?>
</header>

<?php if ($entries === null || count($entries) === 0) : ?>
	<p class="no-entries"><?= e($template->t('authors.none')) ?></p>
<?php else : ?>
	<ul class="authors" role="list">
		<?php foreach ($entries as $author) : ?>
			<li class="authors__item">
				<h2 class="authors__name"><a href="<?= url($template->authorUrl($author, $type)) ?>"><?= e($author->title) ?></a></h2>

				<?php if (! $author->isVirtual() && $author->raw() !== '') : ?>
					<div class="authors__bio">
						<?= raw($author->excerpt()) ?>
					</div>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ul>
<?php endif ?>
