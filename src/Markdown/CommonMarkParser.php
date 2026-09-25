<?php

/**
 * CommonMark parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

use Override;
use Throwable;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use Blush\Core\AppConfig;
use Blush\Event\Dispatcher;
use Blush\Markdown\CommonMark\Directive\DirectiveExtension;
use Blush\Markdown\CommonMark\FigureRenderer;
use Blush\Markdown\CommonMark\ResolveLinks;
use Blush\Markdown\Events\MarkdownEnvironmentBuilding;
use Blush\Media\MediaResolver;

/**
 * The temporary `MarkdownParser` adapter over league/commonmark (D-045,
 * D-080), with Blush's generic directives (D-026) rendered through the
 * `DirectiveRenderer` when one is bound. The converter is built on the first conversion, from
 * `MarkdownConfig` and then any `MarkdownEnvironmentBuilding` listeners, and
 * reused after that.
 */
final class CommonMarkParser implements MarkdownParser
{
	private ?MarkdownConverter $converter = null;

	private readonly MarkdownContext $context;

	public function __construct(
		private readonly MarkdownConfig $config,
		private readonly Dispatcher $events,
		private readonly ?MediaResolver $media = null,
		private readonly ?AppConfig $app = null,
		private readonly ?DirectiveRenderer $directives = null
	) {
		$this->context = new MarkdownContext();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toHtml(string $markdown, string $base = ''): string
	{
		$this->context->base = trim($base, '/');

		try {
			return $this->converter()->convert($markdown)->getContent();
		} catch (MarkdownException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new MarkdownException(sprintf('Unable to convert Markdown: %s', $e->getMessage()), previous: $e);
		} finally {
			$this->context->base = '';
		}
	}

	/**
	 * Returns the converter, building it on first use.
	 *
	 * @throws MarkdownException
	 */
	private function converter(): MarkdownConverter
	{
		if ($this->converter !== null) {
			return $this->converter;
		}

		self::assertClasses('extensions', $this->config->extensions, ExtensionInterface::class);
		self::assertClasses('inlineParsers', $this->config->inlineParsers, InlineParserInterface::class);

		try {
			$environment = new Environment($this->config->options);

			foreach ($this->config->extensions as $extension) {
				$environment->addExtension(new $extension());
			}

			foreach ($this->config->inlineParsers as $parser) {
				$environment->addInlineParser(new $parser());
			}

			$environment->addEventListener(
				DocumentParsedEvent::class,
				new ResolveLinks($this->context, $this->media, $this->app, $this->config->absoluteLinks),
				-100
			);

			if ($this->config->directives) {
				$environment->addExtension(new DirectiveExtension($this->directives));
			}

			if ($this->config->figures) {
				$environment->addRenderer(Paragraph::class, new FigureRenderer(), 10);
			}
		} catch (Throwable $e) {
			throw new MarkdownException(sprintf('Unable to build the Markdown environment: %s', $e->getMessage()), previous: $e);
		}

		$this->events->dispatch(new MarkdownEnvironmentBuilding($environment));

		return $this->converter = new MarkdownConverter($environment);
	}

	/**
	 * Checks that every configured class implements its contract.
	 *
	 * @param  list<string> $classes
	 * @throws MarkdownException
	 */
	private static function assertClasses(string $key, array $classes, string $contract): void
	{
		foreach ($classes as $class) {
			if (! is_subclass_of($class, $contract)) {
				throw new MarkdownException(sprintf(
					'MarkdownConfig "%s" must list %s classes; "%s" is not one.',
					$key,
					$contract,
					$class
				));
			}
		}
	}
}
