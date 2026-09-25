<?php

/**
 * Class plan.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Plan;

use Blush\Container\Attributes\ContextualAttribute;
use Blush\Container\Attributes\SingletonWhen;
use Blush\Container\Attributes\Tag;

/**
 * The resolution plan for one class: whether it can be instantiated, its
 * constructor parameters, and the class-level container attributes
 * (`#[Singleton]`, `#[SingletonWhen]`, `#[Tag]`). It's built from reflection
 * once and can be compiled to a PHP file (D-044), so production requests never
 * reflect a class the container has already planned.
 */
final readonly class ClassPlan
{
	/**
	 * @param class-string                     $class
	 * @param ?list<ParameterPlan>             $parameters `null` when the class has no constructor.
	 * @param ?AttributeSpec<SingletonWhen>    $singletonWhen
	 * @param list<AttributeSpec<Tag>>         $tags
	 */
	public function __construct(
		public string $class,
		public bool $instantiable = true,
		public ?array $parameters = null,
		public bool $singleton = false,
		public ?AttributeSpec $singletonWhen = null,
		public array $tags = []
	) {}

	/**
	 * Whether the plan can be written to a compiled PHP file.
	 */
	public function isExportable(): bool
	{
		return ($this->singletonWhen === null || $this->singletonWhen->isExportable())
			&& array_all($this->tags, static fn (AttributeSpec $tag): bool => $tag->isExportable())
			&& array_all($this->parameters ?? [], static fn (ParameterPlan $param): bool => $param->isExportable());
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'class'         => $this->class,
			'instantiable'  => $this->instantiable,
			'parameters'    => $this->parameters === null ? null : array_map(
				static fn (ParameterPlan $param): array => $param->toArray(),
				$this->parameters
			),
			'singleton'     => $this->singleton,
			'singletonWhen' => $this->singletonWhen?->toArray(),
			'tags'          => array_map(
				static fn (AttributeSpec $tag): array => $tag->toArray(),
				$this->tags
			)
		];
	}

	/**
	 * Rebuilds a plan from the array `toArray()` produced.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		/**
		 * @var array{
		 *     class: class-string,
		 *     instantiable: bool,
		 *     parameters: ?list<array{
		 *         name: string,
		 *         position: int,
		 *         alternatives: list<list<class-string>>,
		 *         typeName: ?string,
		 *         typeString: ?string,
		 *         variadic: bool,
		 *         nullable: bool,
		 *         hasDefault: bool,
		 *         default: mixed,
		 *         noAutowire: bool,
		 *         contextual: ?array{class: class-string<ContextualAttribute>, arguments: array<array-key, mixed>}
		 *     }>,
		 *     singleton: bool,
		 *     singletonWhen: ?array{class: class-string<SingletonWhen>, arguments: array<array-key, mixed>},
		 *     tags: list<array{class: class-string<Tag>, arguments: array<array-key, mixed>}>
		 * } $data
		 */
		/** @var ?AttributeSpec<SingletonWhen> $singletonWhen */
		$singletonWhen = $data['singletonWhen'] === null ? null : AttributeSpec::fromArray($data['singletonWhen']);

		/** @var list<AttributeSpec<Tag>> $tags */
		$tags = array_map(AttributeSpec::fromArray(...), $data['tags']);

		return new self(
			class: $data['class'],
			instantiable: $data['instantiable'],
			parameters: $data['parameters'] === null ? null : array_map(
				ParameterPlan::fromArray(...),
				$data['parameters']
			),
			singleton: $data['singleton'],
			singletonWhen: $singletonWhen,
			tags: $tags
		);
	}
}
