<?php

/**
 * Extension license tests.
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
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionLicense;

#[CoversClass(ExtensionLicense::class)]
final class ExtensionLicenseTest extends TestCase
{
	public function testLinksCommonLicensesWithoutRegardToCase(): void
	{
		$this->assertSame('https://spdx.org/licenses/MIT.html', ExtensionLicense::url('mit'));
		$this->assertSame('https://spdx.org/licenses/GPL-2.0-or-later.html', ExtensionLicense::url('GPL-2.0-or-later'));
		$this->assertSame('https://spdx.org/licenses/GPL-2.0+.html', ExtensionLicense::url('GPL-2.0+'), 'Deprecated identifiers many packages still use.');
		$this->assertNull(ExtensionLicense::url('proprietary'));
		$this->assertNull(ExtensionLicense::url('Acme-Custom'));
	}

	public function testSplitsComposersForms(): void
	{
		$texts = static fn (string $license): array => array_map(
			static fn (array $part): string => $part['url'] === null ? $part['text'] : "[{$part['text']}]",
			ExtensionLicense::parts($license)
		);

		$this->assertSame(['[MIT]'], $texts('MIT'));
		$this->assertSame(['[MIT]', 'or', '[GPL-2.0-or-later]'], $texts('MIT or GPL-2.0-or-later'), 'A list, as ComposerJson::license() joins it.');
		$this->assertSame(['[LGPL-2.1-only]', 'or', '[GPL-3.0-or-later]'], $texts('(LGPL-2.1-only or GPL-3.0-or-later)'));
		$this->assertSame(['[LGPL-2.1-only]', 'and', '[GPL-3.0-or-later]'], $texts('(LGPL-2.1-only AND GPL-3.0-or-later)'), 'Conjunctive, with SPDX\'s uppercase operators.');
		$this->assertSame(['[GPL-2.0-or-later]', 'with', 'Classpath-exception-2.0'], $texts('GPL-2.0-or-later WITH Classpath-exception-2.0'));
		$this->assertSame(['proprietary'], $texts('proprietary'));
		$this->assertSame([], ExtensionLicense::parts(''));
		$this->assertTrue(ExtensionLicense::parts('MIT or ISC')[1]['operator']);
	}

	public function testManifestsTakeAStringOrAList(): void
	{
		$this->assertSame('MIT', ExtensionLicense::fromManifest(' MIT '));
		$this->assertSame('MIT or GPL-2.0-or-later', ExtensionLicense::fromManifest(['MIT', 'GPL-2.0-or-later']), 'A list, any of which applies, as Composer has it.');
		$this->assertSame('', ExtensionLicense::fromManifest(''));
		$this->assertSame('', ExtensionLicense::fromManifest([]), 'An empty list is none.');

		foreach ([5, ['MIT', 5], ['MIT', ' '], ['a' => 'MIT']] as $license) {
			try {
				ExtensionLicense::fromManifest($license);
				$this->fail((string) json_encode($license) . ' should be rejected.');
			} catch (ExtensionException $error) {
				$this->assertStringContainsString('"license" must be a string, or a list', $error->getMessage());
			}
		}
	}
}
