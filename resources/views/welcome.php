<?php

/**
 * The welcome page a new site shows at `/` until it has a homepage.
 *
 * @var Blush\View\Template $template
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

$template->layout('base');

?>
<article class="entry entry--welcome">
	<header class="entry__header">
		<h1 class="entry__title"><?= e($template->t('welcome.title', site: $site->name)) ?></h1>
	</header>

	<div class="entry__content">
		<?= $template->include('partials/welcome') ?>
	</div>
</article>
