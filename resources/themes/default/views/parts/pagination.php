<?php

/**
 * Previous and next page links for a listing.
 *
 * @var Blush\View\Template            $template
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

$entries  = $page->entries;
$previous = $entries?->previous() === null ? null : $page->pageUrl($entries->previous());
$next     = $entries?->next() === null ? null : $page->pageUrl($entries->next());

?>
<?php if ($entries !== null && ($previous !== null || $next !== null)) : ?>
	<nav class="pagination" aria-label="<?= attr($template->t('pagination.label')) ?>">
		<?php if ($previous !== null) : ?>
			<a class="pagination__link pagination__link--prev" rel="prev" href="<?= url($previous) ?>"><?= e($template->t('pagination.previous')) ?></a>
		<?php endif ?>

		<span class="pagination__status"><?= e($template->t('pagination.page', page: $entries->page, pages: $entries->pages())) ?></span>

		<?php if ($next !== null) : ?>
			<a class="pagination__link pagination__link--next" rel="next" href="<?= url($next) ?>"><?= e($template->t('pagination.next')) ?></a>
		<?php endif ?>
	</nav>
<?php endif ?>
