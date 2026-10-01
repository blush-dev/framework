<?php

/**
 * Content service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Override;
use Blush\Container\Container;
use Blush\Container\ServiceResolver;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Events\ContentIndexed;
use Blush\Content\Http\CollectionController;
use Blush\Content\Http\DateArchiveController;
use Blush\Content\Http\HomeController;
use Blush\Content\Http\PageController;
use Blush\Content\Http\SingleController;
use Blush\Content\Http\TermController;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexFingerprint;
use Blush\Content\Index\Indexer;
use Blush\Content\Index\PhpIndex;
use Blush\Content\Index\RecordBuilder;
use Blush\Content\Lint\Linter;
use Blush\Content\Parser\DataDocumentParser;
use Blush\Content\Parser\DocumentParserRegistrar;
use Blush\Content\Parser\DocumentParserRegistry;
use Blush\Content\Parser\DocumentParsers;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Parser\HtmlDocumentParser;
use Blush\Content\Parser\MarkdownDocumentParser;
use Blush\Content\Routing\ContentExportUrls;
use Blush\Content\Routing\ContentRedirects;
use Blush\Content\Routing\ContentRoutes;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Routing\DataRedirects;
use Blush\Content\Routing\PageRoutes;
use Blush\Content\Routing\RefreshRouteCache;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Core\AppConfig;
use Blush\Core\ServiceProvider;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Export\UrlSource;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Routing\RedirectSource;
use Blush\Routing\RouteSource;

/**
 * Binds the content layer: field types, content types, document parsers,
 * the source, the index, and the repository. Everything is built on first
 * use. The field and parser registries start with the built-ins; an
 * extension adds to them in a `resolving()` callback, and adds content
 * types by tagging a `ContentTypeSource` with `ContentTypeSource::TAG`.
 * The source, index, and repository are defaults an extension can replace
 * by binding its own (D-003).
 */
final class ContentServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		FieldFactory::class,
		FrontMatter::class,
		DocumentParsers::class,
		ContentTypeCache::class,
		RecordBuilder::class,
		EntryHydrator::class,
		Indexer::class,
		IndexFingerprint::class,
		ContentUrls::class,
		DocumentEditor::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		ContentSource::class     => FilesystemSource::class,
		ContentIndex::class      => PhpIndex::class,
		ContentRepository::class => IndexedRepository::class,
		ContentWriter::class     => FilesystemWriter::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		ContentTypeLoader::class,
		MarkdownDocumentParser::class,
		HtmlDocumentParser::class,
		DataDocumentParser::class,
		Linter::class,
		ContentRoutes::class,
		PageRoutes::class,
		ContentExportUrls::class,
		ContentRedirects::class,
		DataRedirects::class,
		RefreshRouteCache::class,
		HomeController::class,
		CollectionController::class,
		DateArchiveController::class,
		SingleController::class,
		TermController::class,
		PageController::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [
			ContentRoutes::class,
			PageRoutes::class
		],
		UrlSource::TAG => [ContentExportUrls::class]
	];

	/**
	 * Binds the registries, the field context, and the content types.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			FieldRegistry::class,
			static function (): FieldRegistry {
				$registry = new FieldRegistry();
				new FieldRegistrar($registry)->register();

				return $registry;
			}
		);

		$this->container->singleton(
			DocumentParserRegistry::class,
			static function (): DocumentParserRegistry {
				$registry = new DocumentParserRegistry();
				new DocumentParserRegistrar($registry)->register();

				return $registry;
			}
		);

		$this->container->singleton(
			FieldContext::class,
			static fn (ServiceResolver $resolver): FieldContext => new FieldContext($resolver->make(AppConfig::class)->timezone())
		);

		$this->container->singleton(
			ContentTypes::class,
			static fn (Container $container): ContentTypes => $container->make(ContentTypeCache::class)->load()
		);
	}

	/**
	 * Adds the content redirect sources, after the ones providers declared
	 * so `config/routes.php` redirects win, then data-file redirects, then
	 * `redirect_from` front matter. Also keeps a compiled route table's
	 * redirects current as content changes.
	 */
	#[Override]
	public function boot(): void
	{
		$this->container->tag([DataRedirects::class, ContentRedirects::class], RedirectSource::TAG);

		if ($this->container->has(ListenerRegistry::class)) {
			$this->container->make(ListenerRegistry::class)->listen(ContentIndexed::class, RefreshRouteCache::class);
		}
	}
}
