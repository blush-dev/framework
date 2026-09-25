<?php

/**
 * Parameter plan.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Plan;

use Closure;
use Blush\Container\Attributes\ContextualAttribute;

/**
 * Everything the container needs to know to resolve one parameter, captured as
 * plain data so it can be compiled ahead of time instead of reflected on every
 * request.
 *
 * `alternatives` is the parameter's type in disjunctive normal form: a list of
 * alternatives, each a list of class names a single object must satisfy
 * together (one for a plain class, several for an intersection). Built-in
 * types contribute nothing.
 *
 * A default value that is an object (`new` in an initializer) is not stored.
 * Instead, `defaultFactory` evaluates it fresh when needed, exactly as PHP
 * would. Such a plan is not exportable.
 */
final readonly class ParameterPlan
{
	/**
	 * @param list<list<class-string>>                        $alternatives
	 * @param ?AttributeSpec<ContextualAttribute>              $contextual
	 * @param ?Closure(): mixed                                $defaultFactory
	 */
	public function __construct(
		public string $name,
		public int $position,
		public array $alternatives = [],
		public ?string $typeName = null,
		public ?string $typeString = null,
		public bool $variadic = false,
		public bool $nullable = false,
		public bool $hasDefault = false,
		public mixed $default = null,
		public bool $noAutowire = false,
		public ?AttributeSpec $contextual = null,
		public ?Closure $defaultFactory = null
	) {}

	/**
	 * Whether the parameter carries a fallback of its own: a default value
	 * or a nullable type.
	 */
	public function hasFallback(): bool
	{
		return $this->hasDefault || $this->nullable;
	}

	/**
	 * Returns the parameter's default value, evaluating an object default
	 * fresh on each call.
	 */
	public function defaultValue(): mixed
	{
		return $this->defaultFactory !== null
			? ($this->defaultFactory)()
			: $this->default;
	}

	/**
	 * Whether the plan can be written to a compiled PHP file.
	 */
	public function isExportable(): bool
	{
		return $this->defaultFactory === null
			&& Exportable::check($this->default)
			&& ($this->contextual === null || $this->contextual->isExportable());
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'name'         => $this->name,
			'position'     => $this->position,
			'alternatives' => $this->alternatives,
			'typeName'     => $this->typeName,
			'typeString'   => $this->typeString,
			'variadic'     => $this->variadic,
			'nullable'     => $this->nullable,
			'hasDefault'   => $this->hasDefault,
			'default'      => $this->default,
			'noAutowire'   => $this->noAutowire,
			'contextual'   => $this->contextual?->toArray()
		];
	}

	/**
	 * @param array{
	 *     name: string,
	 *     position: int,
	 *     alternatives: list<list<class-string>>,
	 *     typeName: ?string,
	 *     typeString: ?string,
	 *     variadic: bool,
	 *     nullable: bool,
	 *     hasDefault: bool,
	 *     default: mixed,
	 *     noAutowire: bool,
	 *     contextual: ?array{class: class-string<ContextualAttribute>, arguments: array<array-key, mixed>}
	 * } $data
	 */
	public static function fromArray(array $data): self
	{
		/** @var ?AttributeSpec<ContextualAttribute> $contextual */
		$contextual = $data['contextual'] === null ? null : AttributeSpec::fromArray($data['contextual']);

		return new self(
			name: $data['name'],
			position: $data['position'],
			alternatives: $data['alternatives'],
			typeName: $data['typeName'],
			typeString: $data['typeString'],
			variadic: $data['variadic'],
			nullable: $data['nullable'],
			hasDefault: $data['hasDefault'],
			default: $data['default'],
			noAutowire: $data['noAutowire'],
			contextual: $contextual
		);
	}
}
