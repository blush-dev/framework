<?php

/**
 * Translation catalog check tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Translation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Container\ServiceContainer;
use Blush\Data\DataLoader;
use Blush\Data\DataParserRegistrar;
use Blush\Data\DataParserRegistry;
use Blush\Data\YamlParser;
use Blush\Data\SymfonyYamlParser;
use Blush\Field\Violation;
use Blush\Tests\TemporaryDirectory;
use Blush\Translation\Catalog;
use Blush\Translation\CatalogCheck;

#[CoversClass(CatalogCheck::class)]
#[CoversClass(Catalog::class)]
final class CatalogCheckTest extends TestCase
{
	use TemporaryDirectory;

	/**
	 * Returns the check's problems as strings.
	 *
	 * @return list<string>
	 */
	private function check(string $domain = 'acme/hello'): array
	{
		$registry = new DataParserRegistry();
		new DataParserRegistrar($registry)->register();

		$container = new ServiceContainer();
		$container->singleton(YamlParser::class, SymfonyYamlParser::class);

		$check = new CatalogCheck(new DataLoader($registry, $container));

		return array_map(
			static fn (Violation $violation): string => "{$violation->severity->value} {$violation}",
			$check->check($this->temporaryDirectory() . '/lang', $domain)
		);
	}

	public function testMatchingCatalogsPass(): void
	{
		$this->writeTemporaryFile('lang/en.json', Catalog::starter('acme/hello'));
		$this->writeTemporaryFile('lang/fr_CA.yaml', "'@@locale': fr-ca\n'@@domain': acme/hello\nhello: Bonjour\n");

		$this->assertSame([], $this->check());
	}

	public function testAnExtensionWithoutCatalogsPasses(): void
	{
		$this->assertSame([], $this->check());
	}

	public function testReportsCatalogsThatDontMatch(): void
	{
		$this->writeTemporaryFile('lang/en.json', '{"@@locale": "fr", "@@domain": "acme/other", "hello": "Hello"}');
		$this->writeTemporaryFile('lang/de.json', '{"hello": "Hallo"}');

		$this->assertSame([
			'notice lang/de: Start it with "@@locale": "de" and "@@domain": "acme/hello", which say what it translates.',
			'warning lang/en: Its "@@locale" is "fr", but the file is for "en".',
			'warning lang/en: Its "@@domain" is "acme/other", but it\'s in "acme/hello".'
		], $this->check());
	}

	public function testReportsCatalogsThatCantBeRead(): void
	{
		$this->writeTemporaryFile('lang/en.json', '{broken');

		$this->assertStringStartsWith('error lang:', $this->check()[0] ?? '');
	}
}
