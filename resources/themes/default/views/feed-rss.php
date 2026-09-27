<?php

/**
 * RSS 2.0 feed. Themes may override it, or add `feed-rss-{type}`.
 *
 * @var Blush\View\Template $template
 * @var Blush\View\Site     $site
 * @var Blush\Feed\Feed     $feed
 */

declare(strict_types=1);

?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
	<title><?= e($feed->title) ?></title>
	<link><?= e($feed->link) ?></link>
	<description><?= e($feed->description) ?></description>
	<atom:link href="<?= attr($feed->feedUrl) ?>" rel="self" type="application/rss+xml"/>
	<language><?= e($feed->language) ?></language>
	<lastBuildDate><?= e($feed->updated->format(DATE_RSS)) ?></lastBuildDate>
	<generator><?= e($site->generator) ?></generator>
<?php foreach ($feed->items as $item) : ?>
	<item>
		<title><?= e($item->title) ?></title>
		<link><?= e($item->url) ?></link>
		<guid isPermaLink="true"><?= e($item->url) ?></guid>
		<pubDate><?= e($item->published->format(DATE_RSS)) ?></pubDate>
<?php foreach ($item->authors as $author) : ?>
		<dc:creator><?= e($author) ?></dc:creator>
<?php endforeach ?>
<?php foreach ($item->categories as $category) : ?>
		<category><?= e($category) ?></category>
<?php endforeach ?>
		<description><?= e($item->summary) ?></description>
<?php if ($item->content !== '') : ?>
		<content:encoded><?= e($item->content) ?></content:encoded>
<?php endif ?>
	</item>
<?php endforeach ?>
</channel>
</rss>
