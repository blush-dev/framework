<?php

/**
 * An error page. The site's `_errors/{status}.md` entry, when it has one,
 * gives the title and message.
 *
 * @var Blush\View\Template        $template
 * @var Blush\Translation\Messages $messages
 * @var string                     $title
 * @var ?Blush\Content\Entry\Entry $entry
 * @var string                     $description
 * @var string                     $message
 */

declare(strict_types=1);

use Blush\Support\Bem;

$bem = new Bem('entry');

$template->layout('base');

?>
<article class="<?= attr($bem->modifier('error')) ?>">
	<header class="<?= attr($bem->element('header')) ?>">
		<h1 class="<?= attr($bem->element('title')) ?>"><?= e($title) ?></h1>
	</header>

	<div class="<?= attr($bem->element('content')) ?>">
		<?php if ($entry !== null && $entry->raw() !== '') : ?>
			<?= raw($entry->content()) ?>
		<?php else : ?>
			<p><?= e($description) ?></p>
		<?php endif ?>

		<?php if ($message !== '') : ?>
			<p class="error-details"><code><?= e($message) ?></code></p>
		<?php endif ?>

		<p><a href="/"><?= e($messages->t('error.home')) ?></a></p>
	</div>
</article>
