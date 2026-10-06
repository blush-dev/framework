<?php

/**
 * Directive extension.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

use Override;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use Blush\Directive\DirectiveRenderer;
use Blush\Directive\DirectiveRules;

/**
 * Adds generic directives (D-026) to CommonMark: container
 * (`:::name[label]{attrs}` … `:::`), leaf (`::name[label]{attrs}`), and
 * inline (`:name[text]{attrs}`), rendered through a `DirectiveRenderer`.
 * Labels are plain text.
 */
final readonly class DirectiveExtension implements ExtensionInterface
{
	public function __construct(
		private ?DirectiveRenderer $renderer = null
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(EnvironmentBuilderInterface $environment): void
	{
		$renderer = new DirectiveNodeRenderer($this->renderer);

		$environment
			->addBlockStartParser(new ContainerDirectiveStartParser($this->renderer instanceof DirectiveRules ? $this->renderer : null), 80)
			->addBlockStartParser(new LeafDirectiveStartParser(), 80)
			->addInlineParser(new InlineDirectiveParser(), 40)
			->addRenderer(ContainerDirective::class, $renderer)
			->addRenderer(LeafDirective::class, $renderer)
			->addRenderer(InlineDirective::class, $renderer)
			->addEventListener(DocumentParsedEvent::class, new CollectOutline(), -200);
	}
}
