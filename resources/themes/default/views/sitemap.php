<?php

/**
 * One content type's sitemap. Themes may override it, or add
 * `sitemap-{type}`.
 *
 * @var Blush\View\Template            $this
 * @var list<Blush\Sitemap\SitemapUrl> $urls
 */

declare(strict_types=1);

?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url) : ?>
	<url>
		<loc><?= e($url->loc) ?></loc>
<?php if ($url->lastmod !== null) : ?>
		<lastmod><?= e($url->lastmod->format(DATE_ATOM)) ?></lastmod>
<?php endif ?>
	</url>
<?php endforeach ?>
</urlset>
