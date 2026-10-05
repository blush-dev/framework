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
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\DescriptionList\Node\Description;
use League\CommonMark\Extension\DescriptionList\Node\DescriptionList;
use League\CommonMark\Extension\DescriptionList\Node\DescriptionTerm;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Parser\MarkdownParser as CommonMarkDocumentParser;
use League\CommonMark\Renderer\HtmlRenderer;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use Blush\Core\AppConfig;
use Blush\Event\Dispatcher;
use Blush\Markdown\CommonMark\BracketedSpan;
use Blush\Markdown\CommonMark\BracketedSpanParser;
use Blush\Markdown\CommonMark\BracketedSpanRenderer;
use Blush\Markdown\CommonMark\Directive\DirectiveExtension;
use Blush\Markdown\CommonMark\Directive\DirectiveNodeRenderer;
use Blush\Markdown\CommonMark\DescriptionAttributes;
use Blush\Markdown\CommonMark\DescriptionListRenderer;
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

	public function __construct(
		private readonly MarkdownConfig $config,
		private readonly Dispatcher $events,
		private readonly ?MediaResolver $media = null,
		private readonly ?AppConfig $app = null,
		private readonly ?DirectiveRenderer $directives = null
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toHtml(string $markdown, string $language = ''): string
	{
		try {
			$converter = $this->converter();

			if ($language === '') {
				return $converter->convert($markdown)->getContent();
			}

			// The document carries its language to its directives (D-459).
			$environment = $converter->getEnvironment();
			$document    = new CommonMarkDocumentParser($environment)->parse($markdown);

			$document->data->set(DirectiveNodeRenderer::LANGUAGE, $language);

			return new HtmlRenderer($environment)->renderDocument($document)->getContent();
		} catch (MarkdownException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new MarkdownException(sprintf('Unable to convert Markdown: %s', $e->getMessage()), previous: $e);
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

			// A config that spreads the defaults may list one again.
			foreach (array_unique($this->config->extensions) as $extension) {
				$environment->addExtension(new $extension());
			}

			foreach ($this->config->inlineParsers as $parser) {
				$environment->addInlineParser(new $parser());
			}

			$environment->addEventListener(
				DocumentParsedEvent::class,
				new ResolveLinks($this->media, $this->app, $this->config->absoluteLinks),
				-100
			);

			if ($this->config->directives) {
				$environment->addExtension(new DirectiveExtension($this->directives));
			}

			// `[text]{.class}` is a span, as in Pandoc (D-305), ahead of
			// the closing bracket parser (30).
			if (in_array(AttributesExtension::class, $this->config->extensions, true)) {
				$environment->addInlineParser(new BracketedSpanParser(), 31);
				$environment->addRenderer(BracketedSpan::class, new BracketedSpanRenderer());
			}

			// league/commonmark's description list renderers leave out
			// attributes (D-282).
			if (in_array(DescriptionListExtension::class, $this->config->extensions, true)) {
				$environment->addEventListener(DocumentParsedEvent::class, new DescriptionAttributes(), -10);

				foreach ([DescriptionList::class, DescriptionTerm::class, Description::class] as $node) {
					$environment->addRenderer($node, new DescriptionListRenderer(), 10);
				}
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
