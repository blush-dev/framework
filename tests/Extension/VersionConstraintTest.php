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
		yield 'hyphen, partial end' => ['2.0.1', '1.0 - 2.0', true];
		yield 'hyphen, above a partial end' => ['2.1.0', '1.0 - 2.0', false];
		yield 'hyphen, full end' => ['2.1.1', '1.0 - 2.1.0', false];
		yield 'hyphen, inside a minor' => ['1.1.5', '1.0 - 1.1', true];
		yield 'exact, padded' => ['1.2', '1.2.0', true];
		yield 'not' => ['1.4.0', '!=1.4.0', false];
		yield 'any' => ['dev-main', '*', true];
		yield 'a branch' => ['dev-main', '^1.0', false];
		yield 'four parts' => ['1.2.3', '1.2.3.0', true];
		yield 'a fourth part' => ['1.2.3.4', '>1.2.3', true];
		yield 'a dev version' => ['2.0.0-dev', '^2.0', true];
		yield 'a beta, caret' => ['2.0.0-beta1', '^2.0', true];
		yield 'a beta, below' => ['2.0.0-beta1', '<2.0', false];
		yield 'a beta, at least' => ['2.0.0-beta1', '>=2.0', true];
		yield 'a beta, above' => ['2.0.0-beta1', '>2.0', false];
		yield 'a beta, below a later beta' => ['2.0.0-beta1', '>=2.0.0-beta2', false];
		yield 'an RC, above a beta' => ['2.0.0-RC1', '>=2.0.0-beta2', true];
		yield 'a short beta' => ['2.0.0-b2', '>=2.0.0-beta2', true];
		yield 'a patch, above its release' => ['1.2.3-p1', '>1.2.3', true];
		yield 'a stability flag' => ['1.0', '^1.0@dev', true];
		yield 'a stability flag, below its stability' => ['2.0.0-alpha1', '>=2.0@beta', false];
		yield 'a stability flag, at its stability' => ['2.0.0-beta1', '>=2.0@beta', true];
		yield 'an exact beta' => ['2.0.0-beta1', '2.0.0', false];
		yield 'a numbered branch' => ['2.0.x-dev', '^2.0', true];
		yield 'its own branch' => ['dev-main', 'dev-main', true];
		yield 'another branch' => ['dev-main', 'dev-feature', false];
		yield 'not a branch' => ['dev-main', '!=dev-feature', true];
		yield 'an alias' => ['1.2.3', 'dev-main as 1.2.3', false];
		yield 'a reference' => ['dev-main', 'dev-main#abc123', true];
		yield 'build metadata' => ['1.2.3+build5', '1.2.3', true];
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
		$this->assertFalse(VersionConstraint::isValid('~>1.2'));
		$this->assertTrue(VersionConstraint::isValid('dev-main as 1.2.3'));
		$this->assertTrue(VersionConstraint::isValid('dev-main#abc123'));
	}

	/**
	 * @return iterable<string, array{string, ?string}>
	 */
	public static function normalized(): iterable
	{
		yield 'padded' => ['1.2', '1.2.0.0'];
		yield 'a v prefix' => ['v1.2.3', '1.2.3.0'];
		yield 'a short stability' => ['2.0-b1', '2.0.0.0-beta1'];
		yield 'an RC' => ['8.5.0RC1', '8.5.0.0-RC1'];
		yield 'a patch' => ['1.0-pl2', '1.0.0.0-patch2'];
		yield 'dev' => ['2.0.0-dev', '2.0.0.0-dev'];
		yield 'build metadata' => ['1.2.3+abc', '1.2.3.0'];
		yield 'a numbered branch' => ['1.x-dev', '1.9999999.9999999.9999999-dev'];
		yield 'a branch' => ['dev-main', 'dev-main'];
		yield 'master' => ['master', 'dev-master'];
		yield 'a date' => ['20240101', '20240101'];
		yield 'not a version' => ['1.0-final', null];
	}

	#[DataProvider('normalized')]
	public function testNormalizesVersions(string $version, ?string $normal): void
	{
		$this->assertSame($normal, VersionConstraint::normalize($version));
	}
}
