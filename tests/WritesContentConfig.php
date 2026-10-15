<?php

/**
 * Writes a scratch site's content setup.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests;

use Blush\Tests\Fixtures\Content\CodeDefinitionsProvider;

/**
 * Writes types and relations where a site keeps them (D-617): each type
 * in `user/data/types/{name}.json` and each relation in
 * `user/data/relations/{name}.json`, with the other content settings in
 * `config/content.php`; or, with `codeConfig()`, as a plugin's code.
 */
trait WritesContentConfig
{
	use TemporaryDirectory;

	/**
	 * Writes a content setup: `types` and `relations` (each a list of
	 * named definitions or a map of names to them) as data files, and the
	 * rest as `ContentConfig::fromArray()`. A type's `fields` given as
	 * definitions are written to the field library, and its `fields` list
	 * their keys (D-863).
	 *
	 * @param array<string, mixed> $config
	 */
	private function contentConfig(array $config): void
	{
		foreach (['types', 'relations'] as $key) {
			$definitions = $config[$key] ?? [];
			unset($config[$key]);

			if (! is_array($definitions)) {
				continue;
			}

			foreach ($definitions as $name => $definition) {
				if (! is_array($definition)) {
					continue;
				}

				$name = is_string($name) ? $name : $definition['name'] ?? null;
				unset($definition['name']);

				if (! is_string($name)) {
					continue;
				}

				if ($key === 'types') {
					$definition = $this->libraryFields($definition);
				}

				$this->writeTemporaryFile("user/data/{$key}/{$name}.json", json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
			}
		}

		if ($config === []) {
			return;
		}

		$source = var_export($config, true);

		$this->writeTemporaryFile('config/content.php', <<<PHP
			<?php

			declare(strict_types=1);

			use Blush\Content\ContentConfig;

			return ContentConfig::fromArray({$source});
			PHP);
	}

	/**
	 * Writes a data type's `fields` as library fields
	 * (`user/data/fields/{name}.json`) and places them by its `layout`
	 * (D-858), since a data type defines none of its own.
	 *
	 * @param  array<array-key, mixed> $definition
	 * @return array<array-key, mixed>
	 */
	private function libraryFields(array $definition): array
	{
		$fields = $definition['fields'] ?? null;

		if (! is_array($fields) || $fields === []) {
			return $definition;
		}

		$placed = [];

		foreach ($fields as $key => $field) {
			// Already a library field's key, a changed one, or a section.
			if (is_int($key) && (is_string($field) || (is_array($field) && (isset($field['field']) || isset($field['section']))))) {
				$placed[] = $field;

				continue;
			}

			if (! is_array($field)) {
				continue;
			}

			$name = is_string($key) ? $key : $field['name'] ?? null;
			unset($field['name']);

			if (! is_string($name)) {
				continue;
			}

			$this->writeTemporaryFile("user/data/fields/{$name}.json", json_encode($field, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
			$placed[] = $name;
		}

		return [...$definition, 'fields' => $placed];
	}

	/**
	 * Defines types and relations in code, as a plugin does: the
	 * `test/code` plugin (`CodeTypes`, `CodeRelations`), turned on in
	 * `config/plugins.php`, reads them from `code.json`.
	 *
	 * @param array{types?: array<string, array<string, mixed>>, relations?: array<string, array<string, mixed>>} $config
	 */
	private function codeConfig(array $config): void
	{
		$this->writeTemporaryFile('code.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('extensions/test/code/plugin.json', json_encode(['name' => 'test/code', 'label' => 'Code', 'namespace' => 'code', 'provider' => CodeDefinitionsProvider::class], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['test/code']);\n");
	}

	/**
	 * Removes what `contentConfig()` wrote, so a test can write another.
	 */
	private function clearContentConfig(): void
	{
		$root = $this->temporaryDirectory();

		foreach (['user/data/types', 'user/data/relations', 'user/data/fields'] as $folder) {
			foreach (glob("{$root}/{$folder}/*") ?: [] as $file) {
				unlink($file);
			}
		}

		if (is_file("{$root}/config/content.php")) {
			unlink("{$root}/config/content.php");
		}
	}
}
