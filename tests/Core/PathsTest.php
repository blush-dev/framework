<?php

/**
 * Paths tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Core;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Environment;
use Blush\Core\InvalidEnvironment;
use Blush\Core\Paths;
use Blush\Support\FilesystemException;

#[CoversClass(Paths::class)]
#[CoversClass(Environment::class)]
final class PathsTest extends TestCase
{
	public function testBuildsTheStandardLayout(): void
	{
		$paths = Paths::fromRoot('/srv/site/');

		$this->assertSame('/srv/site', $paths->root);
		$this->assertSame('/srv/site/user/content', $paths->content);
		$this->assertSame('/srv/site/public', $paths->public);
		$this->assertSame('/srv/site/storage/cache', $paths->cache);
		$this->assertSame('/srv/site/user/extensions', $paths->extensions);
		$this->assertSame('/srv/site/resources/themes', $paths->siteThemes);
	}

	public function testRelocatesThePublicDirectory(): void
	{
		$paths = Paths::fromRoot('/home/me/site', ['public' => '../public_html', 'logs' => '/var/log/blush']);

		$this->assertSame('/home/me/public_html', $paths->public);
		$this->assertSame('/var/log/blush', $paths->logs);
	}

	public function testRejectsUnknownPathNames(): void
	{
		$this->expectException(InvalidArgumentException::class);

		Paths::fromRoot('/srv/site', ['uploads' => 'uploads']);
	}

	public function testRejectsRelativePaths(): void
	{
		$this->expectException(InvalidArgumentException::class);

		Paths::fromRoot('/srv/site')->toArray() |> (static fn (array $paths): Paths => new Paths(...[...$paths, 'root' => 'site']));
	}

	public function testJoinConfinesToTheBase(): void
	{
		$paths = Paths::fromRoot('/srv/site');

		$this->assertSame('/srv/site/user/content/posts/a.md', $paths->join($paths->content, 'posts/a.md'));
		$this->assertSame('user/content/posts/a.md', $paths->relative('/srv/site/user/content/posts/a.md'));

		$this->expectException(FilesystemException::class);

		$paths->join($paths->content, '../../config/app.php');
	}

	public function testEnvironmentParsesShortForms(): void
	{
		$this->assertSame(Environment::Development, Environment::fromString('local'));
		$this->assertSame(Environment::Staging, Environment::fromString('STAGE'));
		$this->assertSame(Environment::Production, Environment::fromString(' prod '));
		$this->assertTrue(Environment::Development->isDevelopment());
		$this->assertTrue(Environment::Production->isProduction());

		$this->expectException(InvalidEnvironment::class);

		Environment::fromString('qa');
	}
}
