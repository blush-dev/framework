<?php

/**
 * Site footer: a "Powered by" line, 1.x's lines brought back (D-450):
 * one of the `powered_by` group, at random.
 *
 * @var Blush\View\Template $template
 */

declare(strict_types=1);

$lines = $template->tGroup('powered_by');

?>
<footer class="site-footer">
	<?php if ($lines !== []) : ?>
		<p><?= e($lines[array_rand($lines)]) ?></p>
	<?php endif ?>
</footer>
