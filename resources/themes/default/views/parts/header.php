<?php

/**
 * Site header: the site title and the primary menu.
 *
 * @var Blush\View\Template $template
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

?>
<header class="site-header">
	<p class="site-title"><a href="/" rel="home"><?= e($site->name) ?></a></p>
	<?= $template->component('menu', name: 'primary') ?>
</header>
