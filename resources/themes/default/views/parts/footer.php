<?php

/**
 * Site footer: the footer region and the credit.
 *
 * @var Blush\View\Template $template
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

?>
<footer class="site-footer">
	<?php if ($template->hasRegion('footer')) : ?>
		<div class="site-footer__region">
			<?= $template->region('footer') ?>
		</div>
	<?php endif ?>
	<p><?= e($template->t('powered_by', generator: $site->generator)) ?></p>
</footer>
