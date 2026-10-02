<?php

/**
 * Media service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Media\Embedded\EmbeddedMetadataReader;
use Blush\Media\Embedded\EmbeddedReaderRegistrar;
use Blush\Media\Embedded\EmbeddedReaderRegistry;
use Blush\Field\FieldTargetSource;
use Blush\Routing\RouteSource;

/**
 * Binds media resolution and the media route.
 */
final class MediaServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		MediaResolver::class,
		MediaSchemas::class,
		Index\MediaIndex::class,
		Index\MediaLibrary::class,
		EmbeddedMetadataReader::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		MediaController::class,
		MediaRoutes::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG       => [MediaRoutes::class],
		FieldTargetSource::TAG => [MediaKindTargets::class]
	];

	/**
	 * The embedded metadata readers' registry, seeded with the built-in
	 * readers (D-289).
	 */
	#[Override]
	public function register(): void
	{
		parent::register();

		$this->container->singleton(
			EmbeddedReaderRegistry::class,
			static function (): EmbeddedReaderRegistry {
				$registry = new EmbeddedReaderRegistry();
				new EmbeddedReaderRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
