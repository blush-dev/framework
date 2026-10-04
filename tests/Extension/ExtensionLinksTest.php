<?php

/**
 * Extension links tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Extension\ComposerJson;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionLinks;
use Blush\Plugin\PluginManifest;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(ExtensionLinks::class)]
final class ExtensionLinksTest extends TestCase
{
	use TemporaryDirectory;

	public function testReadsComposersShapeInTheAdminsOrder(): void
	{
		$links = ExtensionLinks::fromArray([
			'homepage' => 'https://acme.test',
			'support'  => [
				'email'    => 'help@acme.test',
				'rss'      => 'https://acme.test/feed',
				'irc'      => 'ircs://irc.libera.chat:6697/acme',
				'docs'     => 'https://acme.test/docs',
				'security' => 'https://acme.test/security'
			],
			'funding'  => [['type' => 'github', 'url' => 'https://github.com/sponsors/acme'], ['url' => 'https://acme.test/donate']]
		]);

		$this->assertSame(['homepage', 'docs', 'irc', 'rss', 'security', 'email'], array_column($links->links(), 'kind'));
		$this->assertSame('mailto:help@acme.test', array_last($links->links())['url'] ?? null);
		$this->assertSame([['type' => 'github', 'url' => 'https://github.com/sponsors/acme'], ['type' => '', 'url' => 'https://acme.test/donate']], $links->funding);
		$this->assertEquals($links, ExtensionLinks::fromArray($links->toArray()), 'It round-trips through a cached manifest.');
		$this->assertSame([], new ExtensionLinks()->toArray());
	}

	public function testManifestsAreCheckedStrictly(): void
	{
		$cases = [
			'"homepage" must be an http or https URL' => ['homepage' => 'ftp://acme.test'],
			'"support" must be an object'             => ['support' => ['https://acme.test']],
			'"tracker" isn\'t one'                     => ['support' => ['tracker' => 'https://acme.test']],
			'"support.email" must be an email'        => ['support' => ['email' => 'nobody']],
			'"support.irc" must be an irc or ircs URL' => ['support' => ['irc' => 'https://acme.test']],
			'"support.docs" must be an http or https URL' => ['support' => ['docs' => 'javascript:alert(1)']],
			'"funding" must be a list'                => ['funding' => ['url' => 'https://acme.test']],
			'Each of "funding" needs a "url"'         => ['funding' => [['type' => 'github']]],
			'Each of "funding" must be an object'     => ['funding' => [['url' => 'https://acme.test', 'name' => 'x']]]
		];

		foreach ($cases as $message => $data) {
			try {
				ExtensionLinks::fromArray($data);
				$this->fail((string) json_encode($data) . ' should be rejected.');
			} catch (ExtensionException $error) {
				$this->assertStringContainsString($message, $error->getMessage());
			}
		}
	}

	public function testComposerJsonFillsThemLeniently(): void
	{
		$this->writeTemporaryFile('acme/composer.json', (string) json_encode([
			'homepage' => 'https://acme.test',
			'support'  => ['issues' => 'https://acme.test/issues', 'forum' => 'not a url', 'unknown' => 'https://acme.test'],
			'funding'  => [['type' => 'patreon', 'url' => 'https://patreon.com/acme'], ['url' => 'ftp://nope']]
		]));

		$data = ComposerJson::fill(['name' => 'acme/hello', 'support' => ['docs' => 'https://hello.test/docs']], $this->temporaryDirectory() . '/acme');

		$this->assertSame('https://acme.test', $data['homepage'] ?? null);
		$this->assertSame(['docs' => 'https://hello.test/docs'], $data['support'] ?? null, 'The manifest\'s own wins, whole.');
		$this->assertSame([['type' => 'patreon', 'url' => 'https://patreon.com/acme']], $data['funding'] ?? null, 'Only what fits.');

		$plugin = PluginManifest::fromArray([...$data, 'license' => ['MIT', 'ISC'], 'source' => 'local', 'path' => '/x']);

		$this->assertSame('MIT or ISC', $plugin->license, 'A manifest\'s license may be a list.');
		$this->assertSame(['homepage', 'docs'], array_column($plugin->links->links(), 'kind'));
		$this->assertEquals($plugin, PluginManifest::fromArray($plugin->toArray()));
	}
}
