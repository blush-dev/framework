<?php

/**
 * Directive definition.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use BackedEnum;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Blush\Field\Field;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\TextField;

/**
 * A registered directive (D-172, D-534): its name and its class, which
 * says everything else: what it wraps (`CONTENT`), how it's written
 * (`KIND`, required), what a container holds (`HOLDS`), the variants it
 * declares (`VARIANTS`, with the directive's namespace as their
 * registrant), and its props, read from the constructor's scalar and
 * backed-enum parameters (a public or unpromoted parameter; services and
 * private state are left out). A string parameter marked `#[MediaProp]`
 * is a `media` field. Its text (label, description, prop labels) isn't
 * here; it comes from the translation catalogs (see
 * `Views::directiveText()`).
 */
final readonly class DirectiveDefinition
{
	/**
	 * How it's written in Markdown, the only form it works in (D-531).
	 */
	public DirectiveKind $kind;

	/**
	 * @param  class-string<Directive> $class
	 * @throws InvalidArgumentException When the class doesn't declare its `KIND`.
	 */
	public function __construct(
		public DirectiveName $name,
		public string $class
	) {
		$this->kind = $class::KIND ?? throw new InvalidArgumentException(sprintf(
			'%s must declare its KIND: DirectiveKind::Container, Leaf, or Inline.',
			$class
		));
	}

	/**
	 * Returns the variants it declares, Default not included, with the
	 * directive's namespace as their registrant.
	 *
	 * @return list<Variant>
	 */
	public function variants(): array
	{
		return array_map(
			fn (string $variant): Variant => new Variant($variant, $this->name->namespace),
			$this->class::VARIANTS
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
		return $this->class::HOLDS;
	}

	/**
	 * Returns what the directive wraps.
	 */
	public function content(): DirectiveContent
	{
		return $this->class::CONTENT;
	}

	/**
	 * Returns how it's written in Markdown, the only form it works in
	 * (D-531): its class's `KIND`.
	 */
	public function kind(): DirectiveKind
	{
		return $this->kind;
	}

	/**
	 * Returns the directive's props.
	 *
	 * @return list<Field>
	 */
	public function props(): array
	{
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
