<?php

/**
 * Attribute reader tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Support;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Support\Attributes\CachedAttributeReader;
use Blush\Support\Attributes\ReflectionAttributeReader;
use Blush\Tests\Fixtures\Support\Decorated;
use Blush\Tests\Fixtures\Support\Label;
use Blush\Tests\Fixtures\Support\Marker;

#[CoversClass(ReflectionAttributeReader::class)]
#[CoversClass(CachedAttributeReader::class)]
final class AttributeReaderTest extends TestCase
{
	/**
	 * @param  list<Marker> $markers
	 * @return list<string>
	 */
	private function names(array $markers): array
	{
		return array_map(static fn (Marker $marker): string => $marker->name, $markers);
	}

	public function testReadsAttributesOnEveryTarget(): void
	{
		$reader = new ReflectionAttributeReader();

		$this->assertSame(['class', 'again'], $this->names($reader->attributesOn(new ReflectionClass(Decorated::class), Marker::class)));
		$this->assertSame(['property'], $this->names($reader->attributesOn(new ReflectionProperty(Decorated::class, 'value'), Marker::class)));
		$this->assertSame(['method'], $this->names($reader->attributesOn(new ReflectionMethod(Decorated::class, 'run'), Marker::class)));
		$this->assertSame(['parameter'], $this->names($reader->attributesOn(new ReflectionParameter([Decorated::class, 'run'], 'input'), Marker::class)));
	}

	public function testInstanceofFlagMatchesSubclasses(): void
	{
		$reader = new ReflectionAttributeReader();
		$class  = new ReflectionClass(Decorated::class);

		$this->assertSame([], $reader->attributesOn($class, Label::class));
		$this->assertCount(2, $reader->attributesOn($class, Label::class, ReflectionAttribute::IS_INSTANCEOF));
	}

	public function testCachedReaderReturnsTheSameInstances(): void
	{
		$reader = new CachedAttributeReader();

		$first  = $reader->attributesOn(new ReflectionClass(Decorated::class), Marker::class);
		$second = $reader->attributesOn(new ReflectionClass(Decorated::class), Marker::class);
		$fresh  = new ReflectionAttributeReader()->attributesOn(new ReflectionClass(Decorated::class), Marker::class);

		$this->assertSame($first, $second);
		$this->assertNotSame($first[0], $fresh[0]);
	}

	public function testCachedReaderKeysTargetsSeparately(): void
	{
		$reader = new CachedAttributeReader();

		$this->assertSame('method', $reader->attributesOn(new ReflectionMethod(Decorated::class, 'run'), Marker::class)[0]->name);
		$this->assertSame('parameter', $reader->attributesOn(new ReflectionParameter([Decorated::class, 'run'], 'input'), Marker::class)[0]->name);
		$this->assertSame('property', $reader->attributesOn(new ReflectionProperty(Decorated::class, 'value'), Marker::class)[0]->name);
	}
}
