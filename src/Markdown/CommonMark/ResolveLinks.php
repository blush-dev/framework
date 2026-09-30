<?php

/**
 * Markdown link and image resolver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use Blush\Core\AppConfig;
use Blush\Markdown\MarkdownContext;
use Blush\Media\MediaResolver;

/**
 * Rewrites link and image URLs once a document is parsed:
 *
 * - A reference to local media (a `user/media` path, or a file next to
 *   the entry in a page bundle) points at the media URL, and images get
 *   their `width` and `height` from the file (unless they set their own).
 * - With `$absolute` on, root-relative URLs (`/archives/…`) become
 *   absolute on the site's origin, as 1.x rendered them (D-078), so bodies
 *   work unchanged in feeds.
 */
final readonly class ResolveLinks
{
	public function __construct(
		private MarkdownContext $context,
		private ?MediaResolver $media,
		private ?AppConfig $app,
		private bool $absolute
	) {}

	/**
	 * Rewrites the document's links and images.
	 */
	public function __invoke(DocumentParsedEvent $event): void
	{
		// Found first and changed after, so no change can upset the walk.
		$nodes  = [];
		$walker = $event->getDocument()->walker();

		while (($step = $walker->next()) !== null) {
			$node = $step->getNode();

			if ($step->isEntering() && $node instanceof AbstractWebResource) {
				$nodes[] = $node;
			}
		}

		foreach ($nodes as $node) {
			$url  = $node->getUrl();
			$file = $this->media?->resolve($url, $this->context->base);

			if ($file !== null) {
				$url = $file->url;

				if ($node instanceof Image && $file->width !== null && $file->height !== null) {
					$attributes = $node->data->get('attributes', []);
					$attributes = is_array($attributes) ? $attributes : [];

					$node->data->set('attributes', [...$attributes, 'width' => (string) $file->width, 'height' => (string) $file->height, ...$attributes]);
				}
			}

			if ($this->absolute && $this->app !== null && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
				$url = $this->app->absoluteUrl($url);
			}

			$node->setUrl($url);
		}
	}
}
