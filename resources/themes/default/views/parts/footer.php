<?php

/**
 * Site footer.
 *
 * @var Blush\View\Template $template
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

?>
<footer class="site-footer">
	<p><?= e($template->t('powered_by', generator: $site->generator)) ?></p>
</footer>
