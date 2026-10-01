<?php

/**
 * Component definition.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\Fields\BoolField;
use Blush\Content\Schema\Fields\EnumField;
use Blush\Content\Schema\Fields\MediaField;
use Blush\Content\Schema\Fields\NumberField;
use Blush\Content\Schema\Fields\TextField;

/**
 * A registered component (D-172): its name, its class (or `null` for a
 * template-only one), what it wraps, its props as content schema fields,
 * and the variants it declares (D-266). Its text (label, description, prop labels) isn't here; it comes
 * from the translation catalogs (see `Views::componentText()`).
 *
 * A class component's props and content default to what its class says:
 * the constructor's scalar and backed-enum parameters (a public or
 * unpromoted parameter; services and private state are left out), and
 * its `CONTENT` constant. A string parameter marked `#[MediaProp]` is a
 * `media` field. Its variants default to its `VARIANTS` constant, with
 * the component's namespace as their registrant.
 */
final readonly class ComponentDefinition
{
	/**
	 * @param ?class-string<Component> $class
	 * @param ?list<Field>             $props    `null` to read them from the class.
	 * @param ?list<Variant|string>    $variants `null` to read them from the class.
	 */
	public function __construct(
		public ComponentName $name,
		public ?string $class = null,
		private ?ComponentContent $content = null,
		private ?array $props = null,
		private ?array $variants = null
	) {}

	/**
	 * Returns the variants it declares, Default not included. A name given
	 * as a string has the component's namespace as its registrant.
	 *
	 * @return list<Variant>
	 */
	public function variants(): array
	{
		$declared = $this->variants ?? ($this->class === null ? [] : $this->class::VARIANTS);

		return array_map(
			fn (Variant|string $variant): Variant => $variant instanceof Variant ? $variant : new Variant($variant, $this->name->namespace),
			$declared
		);
	}

	/**
	 * Returns what a container holds, when it's only some things (the
	 * class's `HOLDS`), else an empty list.
	 *
	 * @return list<string>
	 */
	public function holds(): array
	{
		return $this->class === null ? [] : $this->class::HOLDS;
	}

	/**
	 * Returns what the component wraps.
	 */
	public function content(): ComponentContent
	{
		return $this->content ?? ($this->class === null ? ComponentContent::None : $this->class::CONTENT);
	}

	/**
	 * Returns the component's props.
	 *
	 * @return list<Field>
	 */
	public function props(): array
	{
		if ($this->props !== null || $this->class === null) {
			return $this->props ?? [];
		}

		$props = [];

		foreach (new ReflectionClass($this->class)->getConstructor()?->getParameters() ?? [] as $parameter) {
			$field = self::field($parameter);

			if ($field !== null) {
				$props[] = $field;
			}
		}

		return $props;
	}

	/**
	 * Returns the names of the props its class marks as links
	 * (`#[LinkProp]`).
	 *
	 * @return list<string>
	 */
	public function links(): array
	{
		if ($this->class === null) {
			return [];
		}

		$links = [];

		foreach (new ReflectionClass($this->class)->getConstructor()?->getParameters() ?? [] as $parameter) {
			if ($parameter->getAttributes(LinkProp::class) !== []) {
				$links[] = $parameter->getName();
			}
		}

		return $links;
	}

	/**
	 * Returns the media field a `#[MediaProp]` parameter is, with its kind,
	 * or `null` when it isn't one.
	 */
	private static function media(ReflectionParameter $parameter): ?MediaField
	{
		$attribute = $parameter->getAttributes(MediaProp::class)[0] ?? null;

		return $attribute === null ? null : new MediaField($parameter->getName(), $attribute->newInstance()->kind);
	}

	/**
	 * Returns the field for a constructor parameter, or `null` when it
	 * isn't a prop.
	 */
	private static function field(ReflectionParameter $parameter): ?Field
	{
		$type = $parameter->getType();

		if (! $type instanceof ReflectionNamedType || ($parameter->isPromoted() && ! self::isPublic($parameter))) {
			return null;
		}

		$name  = $parameter->getName();
		$class = $type->getName();
		$field = match (true) {
			$class === 'string'                   => self::media($parameter) ?? new TextField($name),
			$class === 'int'                      => new NumberField($name, integer: true),
			$class === 'float'                    => new NumberField($name),
			$class === 'bool'                     => new BoolField($name),
			is_a($class, BackedEnum::class, true) => new EnumField($name, self::cases($class)),
			default                               => null
		};

		if ($field === null) {
			return null;
		}

		if (! $parameter->isDefaultValueAvailable()) {
			return $field->required();
		}

		$default = $parameter->getDefaultValue();

		return $field->default($default instanceof BackedEnum ? (string) $default->value : $default);
	}

	/**
	 * Returns whether a promoted parameter's property is public.
	 */
	private static function isPublic(ReflectionParameter $parameter): bool
	{
		return $parameter->getDeclaringClass()?->getProperty($parameter->getName())->isPublic() ?? false;
	}

	/**
	 * Returns a backed enum's values.
	 *
	 * @param  class-string<BackedEnum> $enum
	 * @return list<string>
	 */
	private static function cases(string $enum): array
	{
		return array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());
	}
}
