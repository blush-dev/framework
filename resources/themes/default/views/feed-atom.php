<?php

/**
 * Atom feed. Themes may override it, or add `feed-atom-{type}`.
 *
 * @var Blush\View\Template $this
 * @var Blush\View\Site     $site
 * @var Blush\Feed\Feed     $feed
 */

declare(strict_types=1);

?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="<?= attr($feed->language) ?>">
	<title><?= e($feed->title) ?></title>
<?php if ($feed->description !== '') : ?>
	<subtitle><?= e($feed->description) ?></subtitle>
<?php endif ?>
	<id><?= e($feed->feedUrl) ?></id>
	<link rel="alternate" type="text/html" href="<?= attr($feed->link) ?>"/>
	<link rel="self" type="application/atom+xml" href="<?= attr($feed->feedUrl) ?>"/>
	<updated><?= e($feed->updated->format(DATE_ATOM)) ?></updated>
	<author><name><?= e($site->name) ?></name></author>
	<generator><?= e($site->generator) ?></generator>
<?php foreach ($feed->items as $item) : ?>
	<entry>
		<title><?= e($item->title) ?></title>
		<id><?= e($item->url) ?></id>
		<link rel="alternate" type="text/html" href="<?= attr($item->url) ?>"/>
		<published><?= e($item->published->format(DATE_ATOM)) ?></published>
		<updated><?= e($item->updated->format(DATE_ATOM)) ?></updated>
<?php foreach ($item->authors as $author) : ?>
		<author><name><?= e($author) ?></name></author>
<?php endforeach ?>
<?php foreach ($item->categories as $category) : ?>
		<category term="<?= attr($category) ?>"/>
<?php endforeach ?>
		<summary type="html"><?= e($item->summary) ?></summary>
<?php if ($item->content !== '') : ?>
		<content type="html"><?= e($item->content) ?></content>
<?php endif ?>
	</entry>
<?php endforeach ?>
</feed>
