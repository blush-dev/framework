<?php

/**
 * JSON Feed 1.1. Themes may override it, or add `feed-json-{type}`.
 *
 * @var Blush\View\Template $this
 * @var Blush\Feed\Feed     $feed
 */

declare(strict_types=1);

echo json_encode($feed->jsonFeed(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
