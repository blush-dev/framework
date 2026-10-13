<?php

/**
 * The welcome page a new site shows at `/` until it has a homepage.
 *
 * @var Blush\View\Template        $template
 * @var Blush\View\Site            $site
 * @var Blush\Translation\Messages $messages
 */

declare(strict_types=1);

use Blush\Support\Bem;

$bem = new Bem('entry');

$template->layout('base');

?>
<article class="<?= attr($bem->modifier('welcome')) ?>">
	<header class="<?= attr($bem->element('header')) ?>">
		<h1 class="<?= attr($bem->element('title')) ?>"><?= e($messages->t('welcome.title', site: $site->name)) ?></h1>
	</header>

	<div class="<?= attr($bem->element('content')) ?>">
		<?= $template->include('partials/welcome') ?>
	</div>
</article>
