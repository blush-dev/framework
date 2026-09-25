<?php

/**
 * A listing: a type's collection, a term's archive, a date archive, or
 * the home page's. The landing page or term entry, when there is one,
 * introduces it.
 *
 * @var Blush\View\Template                  $this
 * @var Blush\View\Site                      $site
 * @var Blush\Content\Http\ContentPage       $page
 * @var ?Blush\Content\Entry\Entry           $entry
 * @var ?Blush\Content\Type\ContentType      $type
 * @var string                               $title
 */

declare(strict_types=1);

$this->layout('base');

$heading = match (true) {
	$title !== ''                   => $title,
	$page->kind->value === 'home'   => $site->name,
	default                         => ucfirst($type->name ?? '')
};

?>
<header class="archive-header">
	<h1 class="archive-header__title"><?= e($heading) ?></h1>

	<?php if ($entry !== null && ! $entry->isVirtual() && $entry->raw() !== '') : ?>
		<div class="archive-header__description">
			<?= raw($entry->body()) ?>
		</div>
	<?php endif ?>
</header>

<?= $this->insert('parts/entries', page: $page) ?>
