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
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\DescriptionList\Node\Description;
use League\CommonMark\Extension\DescriptionList\Node\DescriptionList;
use League\CommonMark\Extension\DescriptionList\Node\DescriptionTerm;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\Extension\Highlight\HighlightExtension;
use League\CommonMark\Extension\Mention\MentionExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Parser\MarkdownParser as CommonMarkDocumentParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Blush\Core\AppConfig;
use Blush\Markdown\CommonMark\BracketedSpan;
use Blush\Markdown\CommonMark\BracketedSpanParser;
use Blush\Markdown\CommonMark\BracketedSpanRenderer;
use Blush\Markdown\CommonMark\Directive\ContainerDirective;
use Blush\Markdown\CommonMark\Directive\DirectiveExtension;
use Blush\Markdown\CommonMark\Directive\DirectiveNodeRenderer;
use Blush\Markdown\CommonMark\Directive\InlineDirective;
use Blush\Markdown\CommonMark\Directive\LeafDirective;
use Blush\Markdown\CommonMark\DescriptionAttributes;
use Blush\Markdown\CommonMark\DescriptionListRenderer;
use Blush\Markdown\CommonMark\FigureRenderer;
use Blush\Markdown\CommonMark\HeadingAnchorRenderer;
use Blush\Markdown\CommonMark\MentionLinks;
use Blush\Markdown\CommonMark\ResolveLinks;
use Blush\Markdown\Html\HtmlRules;
use Blush\Markdown\Html\MarkupFinder;
use Blush\Markdown\Html\RawMarkup;
use Blush\Media\MediaResolver;

/**
 * The temporary `MarkdownParser` adapter over league/commonmark (D-045,
 * D-080), with Blush's generic directives (D-026) rendered through the
 * `DirectiveRenderer` when one is bound, and mentions linked through the
 * `MentionResolver` (D-493). league/commonmark is never part of Blush's
 * API (D-492): `MarkdownConfig` says how its dialect renders, and this
 * class turns that into the library's extensions and options. The
 * converter is built on the first conversion and reused after that.
 */
final class CommonMarkParser implements MarkdownParser, MarkupFinder
{
	/**
	 * What a mention's name may be: letters, digits, `-`, and `_`, not
	 * ending in a mark, as a profile's slug is.
	 */
	public const string MENTION = '[A-Za-z0-9](?:[A-Za-z0-9_-]*[A-Za-z0-9])?';

	private ?MarkdownConverter $converter = null;

	public function __construct(
		private readonly MarkdownConfig $config,
		private readonly ?MediaResolver $media = null,
		private readonly ?AppConfig $app = null,
		private readonly ?DirectiveRenderer $directives = null,
		private readonly ?MentionResolver $mentions = null
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
	 * @inheritDoc
	 *
	 * The addresses are links' and images' (a mention's is a profile's),
	 * and directives' option values.
	 */
	#[Override]
	public function find(string $markdown): RawMarkup
	{
		try {
			$document = new CommonMarkDocumentParser($this->converter()->getEnvironment())->parse($markdown);
		} catch (MarkdownException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new MarkdownException(sprintf('Unable to read Markdown: %s', $e->getMessage()), previous: $e);
		}

		$html = [];
		$urls = [];

		foreach ($document->iterator() as $node) {
			if ($node instanceof HtmlBlock || $node instanceof HtmlInline) {
				$html[] = $node->getLiteral();
			} elseif ($node instanceof AbstractWebResource) {
				$urls[] = $node->getUrl();
			} elseif ($node instanceof ContainerDirective || $node instanceof LeafDirective || $node instanceof InlineDirective) {
				array_push($urls, ...array_values(array_filter($node->attributes, is_string(...))));
			}
		}

		return new RawMarkup($html, $urls);
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

		$config = $this->config;

		try {
			$environment = new Environment($this->options());

			// The dialect, the same on every site (D-492).
			$dialect = [
				new CommonMarkCoreExtension(),
				new AutolinkExtension(),
				new StrikethroughExtension(),
				new TableExtension(),
				new TaskListExtension(),
				new FootnoteExtension(),
				new DescriptionListExtension(),
				new HighlightExtension(),
				new AttributesExtension()
			];

			foreach ($dialect as $extension) {
				$environment->addExtension($extension);
			}

			if ($config->smartPunctuation) {
				$environment->addExtension(new SmartPunctExtension());
			}

			if ($config->headingAnchors) {
				$environment->addExtension(new HeadingPermalinkExtension());
				$environment->addRenderer(HeadingPermalink::class, new HeadingAnchorRenderer(), 10);
			}

			if ($config->mentions && $this->mentions !== null) {
				$environment->addExtension(new MentionExtension());
			}

			if ($config->html === RawHtml::Filter) {
				$environment->addExtension(new DisallowedRawHtmlExtension());
			}

			$environment->addEventListener(
				DocumentParsedEvent::class,
				new ResolveLinks($this->media, $this->app, $config->absoluteLinks),
				-100
			);

			if ($config->directives) {
				$environment->addExtension(new DirectiveExtension($this->directives));
			}

			// `[text]{.class}` is a span, as in Pandoc (D-305), ahead of
			// the closing bracket parser (30).
			$environment->addInlineParser(new BracketedSpanParser(), 31);
			$environment->addRenderer(BracketedSpan::class, new BracketedSpanRenderer());

			// league/commonmark's description list renderers leave out
			// attributes (D-282).
			$environment->addEventListener(DocumentParsedEvent::class, new DescriptionAttributes(), -10);

			foreach ([DescriptionList::class, DescriptionTerm::class, Description::class] as $node) {
				$environment->addRenderer($node, new DescriptionListRenderer(), 10);
			}

			if ($config->figures) {
				$environment->addRenderer(Paragraph::class, new FigureRenderer(), 10);
			}
		} catch (Throwable $e) {
			throw new MarkdownException(sprintf('Unable to build the Markdown environment: %s', $e->getMessage()), previous: $e);
		}

		return $this->converter = new MarkdownConverter($environment);
	}

	/**
	 * Returns the library's options for the config.
	 *
	 * @return array<string, mixed>
	 */
	private function options(): array
	{
		$config    = $this->config;
		$anchors   = $config->anchors;
		$footnotes = $config->footnotes;

		$options = [
			'html_input'         => $config->html === RawHtml::Escape ? 'escape' : 'allow',
			'allow_unsafe_links' => $config->html === RawHtml::Allow,
			'renderer'           => ['soft_break' => $config->lineBreaks ? "<br>\n" : "\n"],
			'footnote'           => [
				'container_class'  => $footnotes->container,
				'container_add_hr' => $footnotes->rule,
				'ref_class'        => $footnotes->reference,
				'footnote_class'   => $footnotes->note,
				'backref_class'    => $footnotes->backReference
			],
			'heading_permalink'  => [
				'html_class'      => $anchors->class,
				'symbol'          => $anchors->symbol,
				'title'           => $anchors->title,
				'id_prefix'       => $anchors->prefix,
				'fragment_prefix' => $anchors->prefix,
				'insert'          => $anchors->before ? HeadingPermalinkProcessor::INSERT_BEFORE : HeadingPermalinkProcessor::INSERT_AFTER
			],
			'disallowed_raw_html' => ['disallowed_tags' => HtmlRules::REFUSED]
		];

		if ($config->mentions && $this->mentions !== null) {
			$options['mentions'] = [
				'profile' => [
					'prefix'    => '@',
					'pattern'   => self::MENTION,
					'generator' => new MentionLinks($this->mentions)
				]
			];
		}

		return $options;
	}
}
