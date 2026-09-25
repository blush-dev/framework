<?php

/**
 * Route pattern tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Routing\InvalidRoute;
use Blush\Routing\RoutePattern;
use Blush\Routing\UrlGenerationException;

#[CoversClass(RoutePattern::class)]
final class RoutePatternTest extends TestCase
{
	public function testParsesParametersAndNestedBraces(): void
	{
		$pattern = RoutePattern::parse('/archives/{year:\d{4}}/{slug}');

		$this->assertSame(['year', 'slug'], $pattern->params);
		$this->assertSame(['/archives/', ['year', '\d{4}'], '/', ['slug', '[^/]+']], $pattern->parts);
		$this->assertSame('/archives/(\d{4})/([^/]+)', $pattern->regex());
		$this->assertFalse($pattern->isStatic());
		$this->assertTrue(RoutePattern::parse('/about')->isStatic());
	}

	public function testConstraintsMapOverridesInlineConstraints(): void
	{
		$pattern = RoutePattern::parse('/{year:\d+}', ['year' => '\d{4}']);

		$this->assertSame('\d{4}', $pattern->constraint('year'));
	}

	public function testDefaultConstraintsOnlyFillUnconstrainedParameters(): void
	{
		$pattern = RoutePattern::parse('/{a}/{b:[a-z]+}')->withDefaultConstraints(['a' => '\d+', 'b' => '\d+']);

		$this->assertSame('\d+', $pattern->constraint('a'));
		$this->assertSame('[a-z]+', $pattern->constraint('b'));
	}

	public function testQuotesLiteralText(): void
	{
		$this->assertSame('/feed\.xml', RoutePattern::parse('/feed.xml')->regex());
	}

	/**
	 * @return iterable<string, array{0: string, 1?: array<string, string>}>
	 */
	public static function invalidPatterns(): iterable
	{
		yield 'unclosed brace' => ['/{year'];
		yield 'stray closing brace' => ['/year}'];
		yield 'duplicate parameter' => ['/{a}/{a}'];
		yield 'invalid name' => ['/{1a}'];
		yield 'capturing group' => ['/{a:(\d+)}'];
		yield 'invalid regex' => ['/{a:[}'];
		yield 'unknown constraint' => ['/{a}', ['b' => '\d+']];
	}

	/**
	 * @param array<string, string> $constraints
	 */
	#[DataProvider('invalidPatterns')]
	public function testRejectsInvalidPatterns(string $path, array $constraints = []): void
	{
		$this->expectException(InvalidRoute::class);

		RoutePattern::parse($path, $constraints);
	}

	public function testBuildsPaths(): void
	{
		$pattern = RoutePattern::parse('/files/{path:.+}');

		$this->assertSame('/files/a%20b/c.txt', $pattern->build(['path' => 'a b/c.txt']));
	}

	public function testBuildRequiresEveryParameter(): void
	{
		$this->expectException(UrlGenerationException::class);

		RoutePattern::parse('/{year}')->build([]);
	}

	public function testBuildChecksConstraints(): void
	{
		$this->expectException(UrlGenerationException::class);
		$this->expectExceptionMessage('does not match');

		RoutePattern::parse('/{year:\d{4}}')->build(['year' => '24']);
	}
}
