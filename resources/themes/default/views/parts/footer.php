<?php

/**
 * Site footer: the footer region and a "Powered by" line, 1.x's lines
 * brought back (D-450): one of the `powered_by` group, at random.
 *
 * @var Blush\View\Template $template
 */

declare(strict_types=1);

$lines = $template->tGroup('powered_by');

?>
<footer class="site-footer">
	<?php if ($template->hasRegion('footer')) : ?>
		<div class="site-footer__region">
			<?= $template->region('footer') ?>
		</div>
	<?php endif ?>
	<?php if ($lines !== []) : ?>
		<p><?= e($lines[array_rand($lines)]) ?></p>
	<?php endif ?>
</footer>
