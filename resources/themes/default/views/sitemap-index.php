<?php

/**
 * Sitemap index: one sitemap per content type.
 *
 * @var Blush\View\Template            $this
 * @var list<Blush\Sitemap\SitemapUrl> $sitemaps
 */

declare(strict_types=1);

?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($sitemaps as $sitemap) : ?>
	<sitemap>
		<loc><?= e($sitemap->loc) ?></loc>
<?php if ($sitemap->lastmod !== null) : ?>
		<lastmod><?= e($sitemap->lastmod->format(DATE_ATOM)) ?></lastmod>
<?php endif ?>
	</sitemap>
<?php endforeach ?>
</sitemapindex>
