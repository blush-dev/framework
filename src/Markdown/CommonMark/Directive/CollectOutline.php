<?php

/**
 * Collect outline.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

use League\CommonMark\Environment\EnvironmentAwareInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Normalizer\TextNormalizerInterface;
use League\Config\ConfigurationInterface;

/**
 * Gives a table of contents directive (`::toc`, D-183) the document's
 * outline. When a document has one, every heading gets a link target:
 * its own `id` (from the Attributes extension, say), its heading
 * permalink's (when that extension is on), or a new `id` from the
 * environment's slug normalizer, so it never clashes with a permalink's.
 * The outline (each heading's level, plain text, and target) is set on
 * the directive's node as `blush/outline`.
 *
 * It runs after the permalink (-100) and CommonMark table of contents
 * (-150) listeners, and before the slug history resets (-1000). Only
 * documents with a table of contents get new heading ids, so no other
 * page's markup changes.
 */
final class CollectOutline implements EnvironmentAwareInterface
{
	/**
	 * The directive names that get the outline.
	 */
	public const array NAMES = ['toc', 'blush/toc'];

	private TextNormalizerInterface $slugs;

	private ConfigurationInterface $config;

	/**
	 * @inheritDoc
	 */
	public function setEnvironment(EnvironmentInterface $environment): void
	{
		// Built now, so the per-document history reset is registered
		// before any document is parsed.
		$this->slugs  = $environment->getSlugNormalizer();
		$this->config = $environment->getConfiguration();
	}

	/**
	 * Collects the outline for the document's table of contents.
	 */
	public function __invoke(DocumentParsedEvent $event): void
	{
		$tocs     = [];
		$headings = [];

		foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
			if (($node instanceof LeafDirective || $node instanceof ContainerDirective) && in_array($node->name, self::NAMES, true)) {
				$tocs[] = $node;
			} elseif ($node instanceof Heading) {
				$headings[] = $node;
			}
		}

		if ($tocs === []) {
			return;
		}

		$outline = [];

		foreach ($headings as $heading) {
			$text = trim(StringContainerHelper::getChildText($heading, [RawMarkupContainerInterface::class]));

			if ($text !== '') {
				$outline[] = ['level' => $heading->getLevel(), 'text' => $text, 'id' => $this->target($heading, $text)];
			}
		}

		foreach ($tocs as $toc) {
			$toc->data->set('blush/outline', $outline);
		}
	}

	/**
	 * Returns a heading's link target, giving it an `id` when it has
	 * none.
	 */
	private function target(Heading $heading, string $text): string
	{
		$id = $heading->data->get('attributes/id', null);

		if (is_string($id) && $id !== '') {
			return $id;
		}

		$permalink = self::permalink($heading);

		if ($permalink !== null) {
			$prefix = $this->config->get('heading_permalink/fragment_prefix');

			return (is_string($prefix) && $prefix !== '' ? "{$prefix}-" : '') . $permalink->getSlug();
		}

		$length = $this->config->get('slug_normalizer/max_length');
		$id     = $this->slugs->normalize($text, ['node' => $heading, 'length' => is_int($length) ? $length : 255]);

		$heading->data->set('attributes/id', $id);

		return $id;
	}

	/**
	 * Returns a heading's permalink, if it has one.
	 */
	private static function permalink(Node $heading): ?HeadingPermalink
	{
		foreach ($heading->children() as $child) {
			if ($child instanceof HeadingPermalink) {
				return $child;
			}
		}

		return null;
	}
}
