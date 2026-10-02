<?php

/**
 * Extension author tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionException;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(ExtensionAuthor::class)]
final class ExtensionAuthorTest extends TestCase
{
	use TemporaryDirectory;

	public function testReadsComposersShape(): void
	{
		$authors = ExtensionAuthor::list([
			['name' => ' Jane Doe ', 'email' => 'jane@example.test', 'homepage' => 'https://example.test', 'role' => 'Developer'],
			['name' => 'Sam']
		]);

		$this->assertSame(['name' => 'Jane Doe', 'email' => 'jane@example.test', 'homepage' => 'https://example.test', 'role' => 'Developer'], $authors[0]->toArray());
		$this->assertSame(['name' => 'Sam'], $authors[1]->toArray(), 'Empty values are left out.');
		$this->assertSame([], ExtensionAuthor::list([]));
	}

	/**
	 * @return iterable<string, array{mixed}>
	 */
	public static function brokenLists(): iterable
	{
		yield 'not a list'           => [['name' => 'Jane']];
		yield 'a string'             => ['Jane'];
		yield 'an entry without name' => [[['email' => 'jane@example.test']]];
		yield 'an empty name'        => [[['name' => ' ']]];
		yield 'an unknown key'       => [[['name' => 'Jane', 'twitter' => '@jane']]];
		yield 'a bad email'          => [[['name' => 'Jane', 'email' => 'jane']]];
		yield 'a homepage that isn\'t http' => [[['name' => 'Jane', 'homepage' => 'javascript:alert(1)']]];
		yield 'a role that isn\'t text' => [[['name' => 'Jane', 'role' => 5]]];
	}

	#[DataProvider('brokenLists')]
	public function testRefusesBrokenLists(mixed $data): void
	{
		$this->expectException(ExtensionException::class);

		ExtensionAuthor::list($data);
	}

	public function testReadsComposerJsonLeniently(): void
	{
		$this->writeTemporaryFile('theme/composer.json', json_encode(['name' => 'acme/nova', 'authors' => [
			['name' => 'Jane Doe', 'homepage' => 'https://example.test'],
			['email' => 'nameless@example.test'],
			'Sam'
		]]) ?: '');
		$this->writeTemporaryFile('broken/composer.json', '{broken');

		$authors = ExtensionAuthor::fromComposer($this->temporaryDirectory() . '/theme');

		$this->assertSame([['name' => 'Jane Doe', 'homepage' => 'https://example.test']], array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $authors), 'Entries that don\'t fit are skipped.');
		$this->assertSame([], ExtensionAuthor::fromComposer($this->temporaryDirectory() . '/broken'));
		$this->assertSame([], ExtensionAuthor::fromComposer($this->temporaryDirectory() . '/missing'));
	}
}
