<?php

/**
 * The welcome page a new site shows at `/` until it has a home page.
 *
 * @var Blush\View\Template $this
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

$this->layout('base');

?>
<article class="entry entry--welcome">
	<header class="entry__header">
		<h1 class="entry__title"><?= e($this->t('welcome.title', site: $site->name)) ?></h1>
	</header>

	<div class="entry__content">
		<p><?= e($this->t('welcome.running', generator: $site->generator)) ?></p>
		<p><?= e($this->t('welcome.start', path: 'user/content/index.md')) ?></p>
	</div>
</article>
