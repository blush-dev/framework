<?php

/**
 * Export service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Export\Host\HostFilesFactory;
use Blush\Export\Host\HostFilesRegistrar;
use Blush\Export\Host\HostFilesRegistry;

/**
 * Binds static export: the exporter, the export application it boots,
 * and the fingerprint; the crawler and asset copier that run inside that
 * application; and the host file formats (registry seeded with the
 * built-ins, and factory).
 * URL sources are tagged `UrlSource::TAG` by the providers of the
 * subsystems they list (content, feeds, sitemaps).
 */
final class ExportServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Exporter::class,
		ExportSite::class,
		ExportFingerprint::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		Crawler::class,
		ExportAssets::class,
		HostFilesFactory::class
	];

	/**
	 * Binds the host format registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			HostFilesRegistry::class,
			static function (): HostFilesRegistry {
				$registry = new HostFilesRegistry();
				new HostFilesRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
