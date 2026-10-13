<?php

/**
 * Page links for a listing, when the theme has no `partials/pagination`
 * (D-632).
 *
 * @var Blush\Translation\Messages     $messages
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

use Blush\Content\Query\PageLinkKind;

$label = static fn (Blush\Content\Query\PageLink $link): Stringable|string => match ($link->kind) {
	PageLinkKind::Previous => $messages->t('pagination.previous'),
	PageLinkKind::Next     => $messages->t('pagination.next'),
	PageLinkKind::Dots     => '…',
	default                => (string) $link->number
};

?>
<?php if ($links = $page->pageLinks()) : ?>
	<nav aria-label="<?= attr($messages->t('pagination.label')) ?>">
		<ul>
			<?php foreach ($links as $link) : ?>
				<li>
					<?php if ($link->url !== null) : ?>
						<a href="<?= url($link->url) ?>"><?= e($label($link)) ?></a>
					<?php else : ?>
						<span<?= $link->isCurrent() ? ' aria-current="page"' : '' ?>><?= e($label($link)) ?></span>
					<?php endif ?>
				</li>
			<?php endforeach ?>
		</ul>
	</nav>
<?php endif ?>
