<?php

/**
 * One entry in a listing: its linked title, byline, and excerpt.
 *
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */

declare(strict_types=1);

use Blush\Support\Bem;

$bem = new Bem('entry');

$link  = $entry->url();
$title = $entry->title !== '' ? $entry->title : $entry->slug;

?>
<article class="<?= attr($bem->modifier('summary')) ?>">
	<h2 class="<?= attr($bem->element('title')) ?>">
		<?php if ($link !== '') : ?>
			<a href="<?= url($link) ?>"><?= e($title) ?></a>
		<?php else : ?>
			<?= e($title) ?>
		<?php endif ?>
	</h2>

	<?= $template->include('partials/entry-meta', entry: $entry) ?>

	<div class="<?= attr($bem->element('excerpt')) ?>">
		<?= raw($entry->excerpt()) ?>
	</div>
</article>
