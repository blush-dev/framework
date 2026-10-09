<?php

/**
 * An error page. The site's `_errors/{status}.md` entry, when it has one,
 * gives the title and message.
 *
 * @var Blush\View\Template        $template
 * @var int                        $status
 * @var string                     $title
 * @var ?Blush\Content\Entry\Entry $entry
 * @var string                     $description
 * @var string                     $message
 */

declare(strict_types=1);

$template->layout('base');

?>
<article class="entry entry--error">
	<header class="entry__header">
		<h1 class="entry__title"><?= e($title) ?></h1>
	</header>

	<div class="entry__content">
		<?php if ($entry !== null && $entry->raw() !== '') : ?>
			<?= raw($entry->content()) ?>
		<?php else : ?>
			<p><?= e($description) ?></p>
		<?php endif ?>

		<?php if ($message !== '') : ?>
			<p class="error-details"><code><?= e($message) ?></code></p>
		<?php endif ?>

		<p><a href="/"><?= e($template->t('error.home')) ?></a></p>
	</div>
</article>
