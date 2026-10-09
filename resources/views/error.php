<?php

/**
 * An error page, when the theme has no `error` view (D-632). The site's
 * `_errors/{status}.md` entry, when it has one, gives the title and
 * message.
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
<article class="entry">
	<h1><?= e($title) ?></h1>

	<?php if ($entry !== null && $entry->raw() !== '') : ?>
		<?= raw($entry->content()) ?>
	<?php else : ?>
		<p><?= e($description) ?></p>
	<?php endif ?>

	<?php if ($message !== '') : ?>
		<p><code><?= e($message) ?></code></p>
	<?php endif ?>

	<p><a href="/"><?= e($template->t('error.home')) ?></a></p>
</article>
