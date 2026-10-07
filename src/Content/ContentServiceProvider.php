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
use Blush\Content\Parser\DocumentParser;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Relation\EntryRelations;
use Blush\Content\Relation\RelationCompiler;
use Blush\Content\Relation\Relations;
use Blush\Content\Routing\ContentRedirects;
use Blush\Content\Routing\ContentRoutes;
use Blush\Content\Routing\ContentSiteUrls;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Routing\DataRedirects;
use Blush\Content\Routing\PageRoutes;
use Blush\Content\Routing\RefreshRouteCache;
use Blush\Content\Source\ContentSource;
use Blush\Content\Storage\ContentStorage;
use Blush\Content\Storage\StorageDriverFactory;
use Blush\Content\Storage\StorageDriverRegistrar;
use Blush\Content\Storage\StorageDriverRegistry;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\ContentTypeTargets;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\DocumentEditor;
use Blush\Core\AppConfig;
use Blush\Core\ServiceProvider;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\FieldSetLoader;
use Blush\Field\FieldSets;
use Blush\Field\FieldTargets;
use Blush\Field\FieldTargetSource;
use Blush\Markdown\MentionResolver;
use Blush\Routing\RedirectSource;
use Blush\Routing\RouteSource;
use Blush\Routing\UrlSource;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageConfig;

/**
 * Binds the content layer: field types, content types, the document
 * parser, the storage, the index, and the repository. Everything is built
 * on first use. The field and storage driver registries start with the
 * built-ins; an extension adds to them in a `resolving()` callback, and
 * adds content types by tagging a `ContentTypeSource` with
 * `ContentTypeSource::TAG`. The source and writer come from the storage
 * driver `StorageConfig` names for content (D-485, D-486); they, the index, and the
 * repository are defaults an extension can replace by binding its own
 * (D-003).
 */
final class ContentServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		FieldFactory::class,
		FieldTargets::class,
		FrontMatter::class,
		DocumentParser::class,
		ContentTypeCache::class,
		RecordBuilder::class,
		EntryHydrator::class,
		Indexer::class,
		IndexFingerprint::class,
		StorageDriverFactory::class,
		ContentUrls::class,
		DocumentEditor::class,
		EntryRelations::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		ContentIndex::class      => PhpIndex::class,
		ContentRepository::class => IndexedRepository::class,
		MentionResolver::class   => ProfileMentions::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		ContentTypeLoader::class,
		FieldSetLoader::class,
		Linter::class,
		ContentRoutes::class,
		PageRoutes::class,
		ContentSiteUrls::class,
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
		RouteSource::TAG       => [
			ContentRoutes::class,
			PageRoutes::class
		],
		UrlSource::TAG         => [ContentSiteUrls::class],
		FieldTargetSource::TAG => [ContentTypeTargets::class]
	];

	/**
	 * Binds the registries, the storage, the field context, and the
	 * content types.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			StorageDriverRegistry::class,
			static function (): StorageDriverRegistry {
				$registry = new StorageDriverRegistry();
				new StorageDriverRegistrar($registry)->register();

				return $registry;
			}
		);

		$this->container->singletonIf(
			ContentStorage::class,
			static fn (ServiceResolver $resolver): ContentStorage => $resolver->make(StorageDriverFactory::class)->make(
				$resolver->make(StorageConfig::class)->driverFor(StorageArea::Content)
			)
		);

		$this->container->singletonIf(
			ContentSource::class,
			static fn (ServiceResolver $resolver): ContentSource => $resolver->make($resolver->make(ContentStorage::class)->source())
		);

		$this->container->singletonIf(
			ContentWriter::class,
			static fn (ServiceResolver $resolver): ContentWriter => $resolver->make($resolver->make(ContentStorage::class)->writer())
		);

		$this->container->singleton(
			FieldRegistry::class,
			static function (): FieldRegistry {
				$registry = new FieldRegistry();
				new FieldRegistrar($registry)->register();

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

		// Relations are made from the types, which carry the site's
		// relation definitions (D-585, D-593).
		$this->container->singleton(
			Relations::class,
			static fn (Container $container): Relations => new RelationCompiler()->compile($container->make(ContentTypes::class))
		);

		// Field sets load, and compile, with the types (D-339).
		$this->container->singleton(
			FieldSets::class,
			static fn (Container $container): FieldSets => $container->make(ContentTypes::class)->sets
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
