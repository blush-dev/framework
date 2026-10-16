# Pagination

`$page->pageLinks()` gives a listing's numbered page links, ready to
loop over: previous, the page numbers, and next. It shows the first and
last pages and one on each side of the current page, with dots for the
rest, so a long listing reads `← 1 … 4 5 6 … 20 →`. A listing with one
page has no links.

```php
<?php

use Blush\Content\Query\PageLinkKind;

?>
<?php if ($links = $page->pageLinks()) : ?>
	<nav class="pagination" aria-label="Pagination">
		<?php foreach ($links as $link) : ?>
			<?php if ($link->url !== null) : ?>
				<a href="<?= url($link->url) ?>"><?= e(match ($link->kind) {
					PageLinkKind::Previous => 'Previous',
					PageLinkKind::Next     => 'Next',
					default                => (string) $link->number
				}) ?></a>
			<?php else : ?>
				<span<?= $link->isCurrent() ? ' aria-current="page"' : '' ?>><?= e($link->isCurrent() ? (string) $link->number : '…') ?></span>
			<?php endif ?>
		<?php endforeach ?>
	</nav>
<?php endif ?>
```

## A link

Each link has a `kind`, a `number` (none for the dots), and a `url`
(none for the current page or the dots). The kinds are `Previous`,
`Number`, `Current`, `Dots`, and `Next`; `$link->kind->value` (`prev`,
`number`, `current`, `dots`, `next`) works as a class name.
`$link->padded(2)` gives the number with leading zeros, such as `02`.

## Fewer or more links

Change how many pages show with `$page->pageLinks(endSize: 2, midSize:
2)`, or leave out previous and next with `adjacent: false`.

## Just previous and next

For just "Previous" and "Next" links, `$entries->previous()` and
`$entries->next()` give the page numbers, and `$page->pageUrl($number)`
their URLs. The default theme's `partials/pagination.php` is a full
example.

On later pages, the page's title gets the page number; see
[Translations](translations.md#page-numbers-in-titles) to reword it.
