<?php

/**
 * Numbered page links for a listing.
 *
 * @var Blush\Translation\Messages     $messages
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

use Blush\Content\Query\PageLinkKind;
use Blush\Support\Bem;

$bem = new Bem('pagination');

$label = static fn (Blush\Content\Query\PageLink $link): Stringable|string => match ($link->kind) {
	PageLinkKind::Previous => $messages->t('pagination.previous'),
	PageLinkKind::Next     => $messages->t('pagination.next'),
	PageLinkKind::Dots     => '…',
	default                => (string) $link->number
};

?>
<?php if ($links = $page->pageLinks()) : ?>
	<nav class="<?= attr($bem->block()) ?>" aria-label="<?= attr($messages->t('pagination.label')) ?>">
		<ul class="<?= attr($bem->element('items')) ?>">
			<?php foreach ($links as $link) : ?>
				<li class="<?= attr($bem->element('item')->modifier($link->kind->value)) ?>">
					<?php if ($link->url !== null) : ?>
						<a class="<?= attr($bem->element('link')) ?>" href="<?= url($link->url) ?>"><?= e($label($link)) ?></a>
					<?php else : ?>
						<span class="<?= attr($bem->element('link')) ?>"<?= $link->isCurrent() ? ' aria-current="page"' : '' ?>><?= e($label($link)) ?></span>
					<?php endif ?>
				</li>
			<?php endforeach ?>
		</ul>
	</nav>
<?php endif ?>
