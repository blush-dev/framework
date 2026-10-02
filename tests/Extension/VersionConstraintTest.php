<?php

/**
 * Version constraint tests.
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
use Blush\Extension\VersionConstraint;

#[CoversClass(VersionConstraint::class)]
final class VersionConstraintTest extends TestCase
{
	/**
	 * @return iterable<string, array{string, string, bool}>
	 */
	public static function versions(): iterable
	{
		yield 'caret' => ['2.4.1', '^2.0', true];
		yield 'caret, next major' => ['3.0.0', '^2.0', false];
		yield 'caret below one' => ['0.3.5', '^0.3', true];
		yield 'caret below one, next minor' => ['0.4.0', '^0.3', false];
		yield 'tilde, patch' => ['1.2.9', '~1.2.3', true];
		yield 'tilde, next minor' => ['1.3.0', '~1.2.3', false];
		yield 'tilde, minor' => ['1.9', '~1.2', true];
		yield 'tilde, next major' => ['2.0', '~1.2', false];
		yield 'at least' => ['8.5.1', '>=8.2', true];
		yield 'at least, below' => ['8.1.0', '>= 8.2', false];
		yield 'range' => ['1.5', '>=1.0 <2.0', true];
		yield 'range, by comma' => ['2.0', '>=1.0,<2.0', false];
		yield 'either' => ['2.1', '^1.0 || ^2.0', true];
		yield 'wildcard' => ['1.2.4', '1.2.*', true];
		yield 'wildcard, outside' => ['1.3.0', '1.2.*', false];
		yield 'x' => ['2.5', '2.x', true];
		yield 'hyphen' => ['1.5', '1.0 - 2.0', true];
		yield 'hyphen, above' => ['2.0.1', '1.0 - 2.0', false];
		yield 'exact, padded' => ['1.2', '1.2.0', true];
		yield 'not' => ['1.4.0', '!=1.4.0', false];
		yield 'any' => ['dev-main', '*', true];
		yield 'a branch' => ['dev-main', '^1.0', false];
		yield 'a dev version' => ['2.0.0-dev', '^2.0', true];
		yield 'a stability flag' => ['1.0', '^1.0@dev', true];
		yield 'a v prefix' => ['v1.4.0', '^1.4', true];
		yield 'not a constraint' => ['1.0', 'soon', false];
	}

	#[DataProvider('versions')]
	public function testChecksVersions(string $version, string $constraint, bool $satisfied): void
	{
		$this->assertSame($satisfied, VersionConstraint::satisfies($version, $constraint));
	}

	public function testKnowsAConstraint(): void
	{
		$this->assertTrue(VersionConstraint::isValid('^2.0 || >=3.1 <4'));
		$this->assertFalse(VersionConstraint::isValid(''));
		$this->assertFalse(VersionConstraint::isValid('soon'));
		$this->assertFalse(VersionConstraint::isValid('^2.0 ||'));
	}
}
