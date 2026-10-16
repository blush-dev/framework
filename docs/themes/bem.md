# BEM classes

Blush names classes the BEM way: a block, its elements (`__`), and
modifiers (`--`). Blocks and components build theirs for you
(`$block->elementClass('title')`). For your own partials, or anything
else that prints HTML, make a `Bem` for the block's name:

```php
<?php

use Blush\Support\Bem;

$bem = new Bem('pagination');

?>
<nav class="<?= attr($bem->block()) ?>">
	<a class="<?= attr($bem->element('link')) ?>" href="/page/2/">2</a>
</nav>
```

| Method | Classes |
|---|---|
| `$bem->block()` | `pagination` |
| `$bem->modifier('next')` | `pagination pagination--next` |
| `$bem->element('item')` | `pagination__item` |
| `$bem->element('item')->modifier('current')` | `pagination__item pagination__item--current` |
| `$bem->element('item')->modifier('current', 'last')` | `pagination__item pagination__item--current pagination__item--last` |
| `$bem->element('item')->when($isCurrent, 'current')` | adds `pagination__item--current` only when `$isCurrent` |
| `$bem->block()->with('alignwide')` | `pagination alignwide` |
| `$card->block()->with($list->element('card'))` | `card list__card`, two blocks on one element |

Every method returns a class you can keep chaining, without changing
the one you started from, and it prints as the full list wherever a
string goes, such as `attr()`. `with()` adds utility classes (a string
or a list) that aren't the block's own, and an empty modifier is
skipped. Names are letters, digits, hyphens, and underscores, starting
with a letter or digit; anything else throws an `InvalidClassName`.
