<?php

/**
 * Data service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Override;
use Blush\Core\ServiceProvider;

/**
 * Binds the YAML parser and the data loader. The parser registry is built
 * on first use and seeded with the built-in formats. An extension adds or
 * replaces a format by calling `register()` on it in a `resolving()`
 * callback on `DataParserRegistry`.
 */
final class DataServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		DataLoader::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		YamlParser::class => SymfonyYamlParser::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		JsonParser::class,
		YamlDataParser::class
	];

	/**
	 * Binds the registry, seeded with the built-in formats.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			DataParserRegistry::class,
			static function (): DataParserRegistry {
				$registry = new DataParserRegistry();
				new DataParserRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
