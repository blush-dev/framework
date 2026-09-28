<?php

/**
 * Numbered page links for a listing.
 *
 * @var Blush\View\Template            $template
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

use Blush\Content\Query\PageLinkKind;

$label = static fn (Blush\Content\Query\PageLink $link): string => match ($link->kind) {
	PageLinkKind::Previous => $template->t('pagination.previous'),
	PageLinkKind::Next     => $template->t('pagination.next'),
	PageLinkKind::Dots     => '…',
	default                => (string) $link->number
};

?>
<?php if ($links = $page->pageLinks()) : ?>
	<nav class="pagination" aria-label="<?= attr($template->t('pagination.label')) ?>">
		<ul class="pagination__items">
			<?php foreach ($links as $link) : ?>
				<li class="pagination__item pagination__item--<?= attr($link->kind->value) ?>">
					<?php if ($link->url !== null) : ?>
						<a class="pagination__link" href="<?= url($link->url) ?>"><?= e($label($link)) ?></a>
					<?php else : ?>
						<span class="pagination__link"<?= $link->isCurrent() ? ' aria-current="page"' : '' ?>><?= e($label($link)) ?></span>
					<?php endif ?>
				</li>
			<?php endforeach ?>
		</ul>
	</nav>
<?php endif ?>
