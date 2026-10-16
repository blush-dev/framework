<?php

/**
 * Extension schema tests.
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
use Blush\Admin\Theme\AdminThemeDiscovery;
use Blush\Admin\Theme\AdminTokens;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionSchema;
use Blush\Extension\ManifestFile;
use Blush\JsonSchema\JsonSchemas;
use Blush\Plugin\ComposerPluginFinder;
use Blush\Theme\ThemeDiscovery;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(ExtensionSchema::class)]
#[CoversClass(ManifestFile::class)]
final class ExtensionSchemaTest extends TestCase
{
	use TemporaryDirectory;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	public function testEachKindsManifestHasASchema(): void
	{
		$this->assertSame(ExtensionSchema::Plugin, ExtensionSchema::of(ExtensionKind::Plugin));
		$this->assertSame(ExtensionSchema::Theme, ExtensionSchema::of(ExtensionKind::Theme));
		$this->assertSame(ExtensionSchema::IconPack, ExtensionSchema::of(ExtensionKind::IconPack));
		$this->assertSame(ExtensionSchema::AdminTheme, ExtensionSchema::of(ExtensionKind::AdminTheme));
	}

	public function testEachVersionsSchemaIsItsOwnFile(): void
	{
		$this->assertSame('theme.v1.schema.json', ExtensionSchema::Theme->file());
		$this->assertSame('icons.v3.schema.json', ExtensionSchema::IconPack->file(3));

		foreach (ExtensionSchema::cases() as $schema) {
			$this->assertFileExists(Framework::path(JsonSchemas::DIRECTORY . '/' . $schema->file()));
		}
	}

	public function testAMissingVersionIsOne(): void
	{
		$this->assertSame(1, ExtensionSchema::Theme->version([], 'theme.json'));
		$this->assertSame(1, ExtensionSchema::Theme->version(['schemaVersion' => 1], 'theme.json'));
	}

	public function testUpgradingLeavesTheVersionOut(): void
	{
		$this->assertSame(['name' => 'acme/nova'], ExtensionSchema::Theme->upgrade(['schemaVersion' => 1, 'name' => 'acme/nova'], 'theme.json'));
	}

	public function testRefusesAVersionThatIsntOne(): void
	{
		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('The "schemaVersion" in theme.json must be a whole number, 1 or more.');

		ExtensionSchema::Theme->upgrade(['schemaVersion' => '1'], 'theme.json');
	}

	public function testRefusesANewerVersion(): void
	{
		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('theme.json is written to schema version 2, and this Blush reads up to version 1; it needs a newer Blush.');

		ExtensionSchema::Theme->upgrade(['schemaVersion' => 2], 'theme.json');
	}

	public function testACurrentFileIsntOutdated(): void
	{
		$this->assertNull(ExtensionSchema::Theme->outdated(['schemaVersion' => 1], 'theme.json'));
		$this->assertNull(ExtensionSchema::Theme->outdated([], 'theme.json'));
		$this->assertNull(ExtensionSchema::Theme->outdated(['schemaVersion' => 9], 'theme.json'), 'Reading it says what\'s wrong with a newer one.');

		$this->writeTemporaryFile('acme/nova/theme.json', '{"schemaVersion": 1, "name": "acme/nova"}');
		$this->writeTemporaryFile('acme/harbor/tokens.json', '{"schemaVersion": 1}');

		$this->assertNull(ManifestFile::outdated($this->temporaryDirectory() . '/acme/nova', ExtensionKind::Theme));
		$this->assertNull(AdminTokens::outdated($this->temporaryDirectory() . '/acme/harbor'));
	}

	public function testAManifestsVersionIsNeverTakenFromComposerJson(): void
	{
		$this->writeTemporaryFile('acme/nova/theme.json', '{"name": "acme/nova"}');
		$this->writeTemporaryFile('acme/nova/composer.json', '{"extra": {"blush": {"schemaVersion": 1, "label": "Nova"}}}');

		$data = ManifestFile::load($this->temporaryDirectory() . '/acme/nova', ExtensionKind::Theme);

		$this->assertSame('Nova', $data['label']);
		$this->assertArrayNotHasKey('schemaVersion', $data);
	}

	public function testComposerJsonsExtraIsReadByItsOwnVersion(): void
	{
		$this->writeTemporaryFile('acme/nova/theme.json', '{"name": "acme/nova"}');
		$this->writeTemporaryFile('acme/nova/composer.json', '{"extra": {"blush": {"schemaVersion": 2}}}');

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('composer.json is written to schema version 2');

		ManifestFile::load($this->temporaryDirectory() . '/acme/nova', ExtensionKind::Theme);
	}

	public function testANewerExtensionIsBroken(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"schemaVersion": 2, "name": "acme/nova"}');
		$this->writeTemporaryFile('extensions/acme/harbor/admin-theme.json', '{"name": "acme/harbor", "label": "Harbor"}');
		$this->writeTemporaryFile('extensions/acme/harbor/tokens.json', '{"schemaVersion": 2}');

		$paths  = Paths::fromRoot($this->temporaryDirectory());
		$themes = new ThemeDiscovery($paths)->discover();
		$admin  = new AdminThemeDiscovery($paths)->discover();

		$this->assertFalse($themes->has('acme/nova'));
		$this->assertStringContainsString('it needs a newer Blush', $themes->invalid()['extensions/acme/nova'] ?? '');
		$this->assertNull($admin->find('acme/harbor'));
		$this->assertStringContainsString('tokens.json is written to schema version 2', (string) ($admin->invalid()['extensions/acme/harbor'] ?? ''));
	}

	public function testANewerComposerPluginIsBroken(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode(['packages' => [
			['name' => 'acme/gallery', 'type' => 'blush-plugin', 'install-path' => '../acme/gallery', 'extra' => ['blush' => ['schemaVersion' => 2]]],
			['name' => 'acme/notes', 'type' => 'blush-plugin', 'install-path' => '../acme/notes', 'extra' => ['blush' => ['schemaVersion' => 1, 'label' => 'Notes']]]
		]]));

		$found = new ComposerPluginFinder($this->temporaryDirectory() . '/vendor')->find();

		$this->assertSame(['acme/notes'], array_map(static fn ($plugin): string => $plugin->name, $found->manifests));
		$this->assertStringContainsString('acme/gallery\'s composer.json is written to schema version 2', $found->broken[0]->reason ?? '');
	}
}
