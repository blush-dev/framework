<?php

/**
 * Content config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\FieldRegistrar;
use Blush\Content\Schema\FieldRegistry;

/**
 * The site's content settings, from `config/content.php`:
 *
 *     return new ContentConfig(
 *         types: [
 *             new ContentType('post', path: '_posts', routing: new TypeRouting('archives')),
 *             new ContentType('category', path: 'topics', taxonomy: true, termCollect: 'post')
 *         ],
 *         home: 'post'
 *     );
 *
 * Types defined here are locked in the admin, and they replace built-in or
 * extension types of the same name. Types may also be defined as data in
 * `user/data/types` unless `dataTypes` is off; with `dataTypeRouting` off,
 * data types can't set their own routing (D-042).
 *
 * With `autoIndex` on (the default), development requests refresh the
 * content index incrementally on first use; elsewhere, `content:index`
 * (or publishing) refreshes it.
 */
final readonly class ContentConfig implements Config
{
	/**
	 * @param  list<ContentType> $types           The site's types.
	 * @param  ?string           $home            A type whose collection is the home page.
	 * @param  bool              $dataTypes       Whether `user/data/types` is read.
	 * @param  bool              $dataTypeRouting Whether data types may set `routing`.
	 * @param  list<string>      $disabled        Built-in types to leave out.
	 * @param  bool              $autoIndex       Whether development requests refresh the index.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public array $types = [],
		public ?string $home = null,
		public bool $dataTypes = true,
		public bool $dataTypeRouting = true,
		public array $disabled = [],
		public bool $autoIndex = true
	) {
		$names = [];

		foreach ($types as $type) {
			if (isset($names[$type->name])) {
				throw new InvalidConfig(sprintf('ContentConfig defines the "%s" type more than once.', $type->name));
			}

			$names[$type->name] = true;
		}

		foreach ($disabled as $name) {
			$builtIn = BuiltInType::tryFrom($name);

			if ($builtIn === null || ! $builtIn->canDisable()) {
				throw new InvalidConfig(sprintf('ContentConfig can\'t disable "%s"; only these built-in types can be: %s.', $name, implode(', ', array_column(
					array_filter(BuiltInType::cases(), static fn (BuiltInType $type): bool => $type->canDisable()),
					'value'
				))));
			}
		}
	}

	/**
	 * @inheritDoc
	 *
	 * `types` may be a list of definitions with names or, as in 1.x, a map
	 * of names to definitions.
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['types', 'home', 'dataTypes', 'dataTypeRouting', 'disabled', 'autoIndex']);

		$types = $data['types'] ?? [];

		if (! is_array($types)) {
			throw new InvalidConfig('ContentConfig "types" must be a list or map of type definitions.');
		}

		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();
		$fields = new FieldFactory($registry);

		$definitions = [];

		foreach ($types as $key => $type) {
			if (! is_array($type)) {
				throw new InvalidConfig('ContentConfig "types" must hold type definitions.');
			}

			try {
				$definitions[] = ContentType::fromArray(is_string($key) ? ['name' => $key, ...$type] : $type, $fields);
			} catch (InvalidContentType $e) {
				throw new InvalidConfig(sprintf('ContentConfig is invalid: %s', $e->getMessage()), previous: $e);
			}
		}

		return new static(
			types: $definitions,
			home: $values->nullableString('home'),
			dataTypes: $values->bool('dataTypes', true),
			dataTypeRouting: $values->bool('dataTypeRouting', true),
			disabled: $values->stringList('disabled'),
			autoIndex: $values->bool('autoIndex', true)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'types'           => array_map(static fn (ContentType $type): array => $type->toArray(), $this->types),
			'home'            => $this->home,
			'dataTypes'       => $this->dataTypes,
			'dataTypeRouting' => $this->dataTypeRouting,
			'disabled'        => $this->disabled,
			'autoIndex'       => $this->autoIndex
		];
	}
}
