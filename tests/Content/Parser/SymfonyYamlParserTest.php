<?php

/**
 * Symfony YAML parser tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Data\InvalidData;
use Blush\Content\Parser\SymfonyYamlParser;

#[CoversClass(SymfonyYamlParser::class)]
#[CoversClass(InvalidData::class)]
final class SymfonyYamlParserTest extends TestCase
{
	public function testParsesPlainData(): void
	{
		$data = new SymfonyYamlParser()->parse("title: Hello\ntags: [a, b]\ncount: 3\ndraft: false\nnothing:\n");

		$this->assertSame(['title' => 'Hello', 'tags' => ['a', 'b'], 'count' => 3, 'draft' => false, 'nothing' => null], $data);
	}

	public function testReturnsTimestampsAsStrings(): void
	{
		$data = new SymfonyYamlParser()->parse(implode("\n", [
			'offset: 2007-03-30 23:17:00 -5',
			'date: 2003-04-15',
			'local: 2003-04-15 10:30:00',
			'utc: 2003-04-15T10:00:00Z',
			'zero: 2003-04-15 10:00:00 +00:00',
			'nested: [2003-04-15]'
		]));

		$this->assertSame([
			'offset' => '2007-03-30T23:17:00-05:00',
			'date'   => '2003-04-15T00:00:00',
			'local'  => '2003-04-15T10:30:00',
			'utc'    => '2003-04-15T10:00:00+00:00',
			'zero'   => '2003-04-15T10:00:00+00:00',
			'nested' => ['2003-04-15T00:00:00']
		], $data);
	}

	public function testRejectsObjectTags(): void
	{
		$this->expectException(InvalidData::class);

		new SymfonyYamlParser()->parse('a: !php/object \'O:8:"stdClass":0:{}\'');
	}

	public function testRejectsMalformedYaml(): void
	{
		$this->expectException(InvalidData::class);
		$this->expectExceptionMessage('Invalid YAML');

		new SymfonyYamlParser()->parse("a: [unclosed\nb: c");
	}
}
