<?php

/**
 * Field configuration.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * The site's field settings, from `config/fields.php` (D-337):
 *
 *     return new FieldConfig(sets: [
 *         new FieldSet('seo', [new TextField('meta_title')], ['type:post', 'type:page'], 'SEO')
 *     ]);
 *
 * Sets defined here are locked in the admin, and they replace an
 * extension's set of the same name. Sets may also be defined as data in
 * `user/data/fields` unless `dataSets` is off; a data set replaces one of
 * the same name from here.
 *
 * In array form (`fromArray()`, and the config cache), `sets` are kept as
 * `definitions` and built when the sets load, with every field type
 * extensions register; the config file runs before they do.
 */
final readonly class FieldConfig implements Config
{
	/**
	 * @param  list<FieldSet>                $sets        The site's sets.
	 * @param  bool                          $dataSets    Whether `user/data/fields` is read.
	 * @param  list<array<array-key, mixed>> $definitions The site's sets in array form, each with a `name`.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public array $sets = [],
		public bool $dataSets = true,
		public array $definitions = []
	) {
		$names = [];

		foreach ($definitions as $definition) {
			if (! is_string($definition['name'] ?? null) || $definition['name'] === '') {
				throw new InvalidConfig('FieldConfig set definitions must each have a "name".');
			}
		}

		foreach ([...array_map(static fn (FieldSet $set): string => $set->name, $sets), ...array_column($definitions, 'name')] as $name) {
			if (isset($names[$name])) {
				throw new InvalidConfig(sprintf('FieldConfig defines the "%s" field set more than once.', $name));
			}

			$names[$name] = true;
		}
	}

	/**
	 * @inheritDoc
	 *
	 * `sets` may be a list of definitions with names or a map of names to
	 * definitions.
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['sets', 'dataSets']);

		$sets = $data['sets'] ?? [];

		if (! is_array($sets)) {
			throw new InvalidConfig('FieldConfig "sets" must be a list or map of field set definitions.');
		}

		$definitions = [];

		foreach ($sets as $key => $set) {
			if (! is_array($set)) {
				throw new InvalidConfig('FieldConfig "sets" must hold field set definitions.');
			}

			$definitions[] = is_string($key) ? ['name' => $key, ...$set] : $set;
		}

		return new static(dataSets: $values->bool('dataSets', true), definitions: $definitions);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'sets'     => [...array_map(static fn (FieldSet $set): array => $set->toArray(), $this->sets), ...$this->definitions],
			'dataSets' => $this->dataSets
		];
	}
}
