<?php

/**
 * Block extension.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Block;

use Override;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use Blush\Block\BlockRenderer;
use Blush\Block\BlockRules;

/**
 * Adds blocks (D-026) to CommonMark: container
 * (`:::name[label]{attrs}` … `:::`), leaf (`::name[label]{attrs}`), and
 * inline (`:name[text]{attrs}`), rendered through a `BlockRenderer`.
 * Labels are plain text.
 */
final readonly class BlockExtension implements ExtensionInterface
{
	public function __construct(
		private ?BlockRenderer $renderer = null
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(EnvironmentBuilderInterface $environment): void
	{
		$renderer = new BlockNodeRenderer($this->renderer);

		$environment
			->addBlockStartParser(new ContainerBlockStartParser($this->renderer instanceof BlockRules ? $this->renderer : null), 80)
			->addBlockStartParser(new LeafBlockStartParser(), 80)
			->addInlineParser(new InlineBlockParser(), 40)
			->addRenderer(ContainerBlock::class, $renderer)
			->addRenderer(LeafBlock::class, $renderer)
			->addRenderer(InlineBlock::class, $renderer)
			->addEventListener(DocumentParsedEvent::class, new CollectOutline(), -200);
	}
}
