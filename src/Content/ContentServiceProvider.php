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
use Blush\Content\Parser\DataDocumentParser;
use Blush\Content\Parser\DocumentParserRegistrar;
use Blush\Content\Parser\DocumentParserRegistry;
use Blush\Content\Parser\DocumentParsers;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Parser\HtmlDocumentParser;
use Blush\Content\Parser\MarkdownDocumentParser;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\FieldRegistrar;
use Blush\Content\Schema\FieldRegistry;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\ServiceProvider;

/**
 * Binds the content layer: field types, content types, and document
 * parsers. Everything is built on first use. The field and parser
 * registries start with the built-ins; an extension adds to them in a
 * `resolving()` callback, and adds content types by tagging a
 * `ContentTypeSource` with `ContentTypeSource::TAG`.
 */
final class ContentServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		FieldFactory::class,
		FrontMatter::class,
		DocumentParsers::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		ContentTypeLoader::class,
		MarkdownDocumentParser::class,
		HtmlDocumentParser::class,
		DataDocumentParser::class
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
			static fn (Container $container): ContentTypes => $container->make(ContentTypeLoader::class)->load()
		);
	}
}
