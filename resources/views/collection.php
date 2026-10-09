<?php

/**
 * A listing, when the theme has no `collection` view (D-632): a type's
 * collection, a term's archive, a date archive, or the homepage's. The
 * landing page or term entry, when there is one, introduces it.
 *
 * @var Blush\View\Template             $template
 * @var Blush\View\Site                 $site
 * @var Blush\Content\Http\ContentPage  $page
 * @var ?Blush\Content\Entry\Entry      $entry
 * @var ?Blush\Content\Type\ContentType $type
 * @var string                          $title
 */

declare(strict_types=1);

$template->layout('base');

$heading = match (true) {
	$title !== ''                   => $title,
	$page->kind->value === 'home'   => $site->name,
	default                         => ucfirst($type->name ?? '')
};

?>
<header>
	<h1><?= e($heading) ?></h1>

	<?php if ($entry !== null && $entry->raw() !== '') : ?>
		<?= raw($entry->content()) ?>
	<?php endif ?>
</header>

<?= $template->include('partials/entries', page: $page) ?>
