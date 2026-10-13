<?php

/**
 * A single entry, with any listing its `collection` front matter asks
 * for.
 *
 * @var Blush\View\Template            $template
 * @var Blush\Content\Http\ContentPage $page
 * @var Blush\Content\Entry\Entry      $entry
 * @var ?Blush\Content\Query\Paginator $entries
 * @var string                         $title
 */

declare(strict_types=1);

use Blush\Support\Bem;

$bem = new Bem('entry');

$template->layout('base');

?>
<article class="<?= attr($bem->modifier('single')) ?>">
	<header class="<?= attr($bem->element('header')) ?>">
		<h1 class="<?= attr($bem->element('title')) ?>"><?= e($title !== '' ? $title : $entry->slug) ?></h1>

		<?php if ($entry->subtitle() !== '') : ?>
			<p class="<?= attr($bem->element('subtitle')) ?>"><?= e($entry->subtitle()) ?></p>
		<?php endif ?>

		<?= $template->include('partials/entry-meta', entry: $entry) ?>
	</header>

	<div class="<?= attr($bem->element('content')) ?>">
		<?= raw($entry->content()) ?>
	</div>
</article>

<?php if ($entries !== null) : ?>
	<?= $template->include('partials/entries', page: $page) ?>
<?php endif ?>
