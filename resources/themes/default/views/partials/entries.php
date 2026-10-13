<?php

/**
 * A page's listed entries, with pagination.
 *
 * @var Blush\View\Template            $template
 * @var Blush\Translation\Messages     $messages
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

use Blush\Support\Bem;

$bem = new Bem('entries');

$entries = $page->entries;

?>
<?php if ($entries === null || count($entries) === 0) : ?>
	<p class="no-entries"><?= e($messages->t('no_entries')) ?></p>
<?php else : ?>
	<ul class="<?= attr($bem->block()) ?>" role="list">
		<?php foreach ($entries as $item) : ?>
			<li class="<?= attr($bem->element('item')) ?>"><?= $template->include('partials/entry-summary', entry: $item) ?></li>
		<?php endforeach ?>
	</ul>

	<?= $template->include('partials/pagination', page: $page) ?>
<?php endif ?>
