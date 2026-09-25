<?php

/**
 * Site footer.
 *
 * @var Blush\View\Template $this
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

?>
<footer class="site-footer">
	<p><?= e($this->t('powered_by', generator: $site->generator)) ?></p>
</footer>
