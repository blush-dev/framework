<?php

/**
 * Command signature.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Input;

use BackedEnum;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidCommand;

/**
 * Everything the console knows about a command class, read once from its
 * `#[Command]` attribute and its `__invoke()` parameters (D-065):
 *
 * - `#[Argument]` parameters become positional arguments, in order. A
 *   parameter with a default (or a nullable one) is optional, and a variadic
 *   one collects the rest.
 * - `#[Option]` parameters become options: `bool` is a flag, `array` is a
 *   repeatable list of strings, and anything else takes a value.
 * - Other parameters are left for the container to resolve.
 *
 * Scalar types, backed enums, and their nullable forms are supported.
 */
final readonly class Signature
{
	/**
	 * @param class-string $class
	 * @param list<string> $aliases
	 */
	public function __construct(
		public string $class,
		public string $name,
		public string $description,
		public array $aliases,
		public bool $hidden,
		public InputDefinition $definition
	) {}

	/**
	 * Reads the signature of a command class.
	 *
	 * @throws InvalidCommand When the class isn't a valid command.
	 */
	public static function fromClass(string $class): self
	{
		if (! class_exists($class)) {
			throw new InvalidCommand(sprintf('Command class "%s" does not exist.', $class));
		}

		$reflection = new ReflectionClass($class);
		$attribute  = array_first($reflection->getAttributes(Command::class))?->newInstance();

		if ($attribute === null) {
			throw new InvalidCommand(sprintf('Command class "%s" has no #[%s] attribute.', $class, Command::class));
		}

		try {
			$invoke = $reflection->getMethod('__invoke');
		} catch (ReflectionException) {
			throw new InvalidCommand(sprintf('Command class "%s" has no __invoke() method.', $class));
		}

		self::assertInvokable($class, $invoke);

		$arguments = [];
		$options   = [];

		foreach ($invoke->getParameters() as $parameter) {
			$argument = array_first($parameter->getAttributes(Argument::class))?->newInstance();
			$option   = array_first($parameter->getAttributes(Option::class))?->newInstance();

			if ($argument !== null && $option !== null) {
				throw self::invalid($class, $parameter, 'cannot be both an argument and an option');
			}

			if ($argument !== null) {
				$arguments[] = self::argument($class, $parameter, $argument, array_last($arguments));
			} elseif ($option !== null) {
				$options[] = self::option($class, $parameter, $option);
			}
		}

		return new self(
			class: $class,
			name: $attribute->name,
			description: $attribute->description,
			aliases: $attribute->aliases,
			hidden: $attribute->hidden,
			definition: new InputDefinition($arguments, $options)
		);
	}

	/**
	 * Returns the usage line: `name [options] [--] <argument>...`.
	 */
	public function usage(): string
	{
		$parts = [$this->name];

		if ($this->definition->options() !== []) {
			$parts[] = '[options]';
		}

		if ($this->definition->arguments !== []) {
			$parts[] = '[--]';

			foreach ($this->definition->arguments as $argument) {
				$parts[] = $argument->usage();
			}
		}

		return implode(' ', $parts);
	}

	/**
	 * Checks that `__invoke()` is public, not static, and returns an
	 * `ExitCode`.
	 *
	 * @throws InvalidCommand
	 */
	private static function assertInvokable(string $class, ReflectionMethod $invoke): void
	{
		if (! $invoke->isPublic() || $invoke->isStatic()) {
			throw new InvalidCommand(sprintf('Command class "%s" must have a public, non-static __invoke().', $class));
		}

		$return = $invoke->getReturnType();

		if (! $return instanceof ReflectionNamedType || $return->getName() !== ExitCode::class) {
			throw new InvalidCommand(sprintf('%s::__invoke() must return %s.', $class, ExitCode::class));
		}
	}

	/**
	 * Builds an argument definition from a parameter.
	 *
	 * @throws InvalidCommand
	 */
	private static function argument(string $class, ReflectionParameter $parameter, Argument $attribute, ?ArgumentDefinition $previous): ArgumentDefinition
	{
		[$type, $enum] = self::valueType($class, $parameter);

		if ($type === ValueType::Bool) {
			throw self::invalid($class, $parameter, 'is a bool argument; use an option instead');
		}

		$optional = $parameter->isVariadic() || $parameter->isDefaultValueAvailable() || $parameter->allowsNull();

		if (! $optional && $previous !== null && ! $previous->required) {
			throw self::invalid($class, $parameter, 'is required but follows an optional argument');
		}

		return new ArgumentDefinition(
			name: self::kebab($parameter->getName()),
			parameter: $parameter->getName(),
			type: $type,
			description: $attribute->description,
			required: ! $optional,
			variadic: $parameter->isVariadic(),
			default: $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
			enum: $enum
		);
	}

	/**
	 * Builds an option definition from a parameter.
	 *
	 * @throws InvalidCommand
	 */
	private static function option(string $class, ReflectionParameter $parameter, Option $attribute): OptionDefinition
	{
		if ($attribute->short !== null && strlen($attribute->short) !== 1) {
			throw self::invalid($class, $parameter, 'has a short name that is not a single character');
		}

		if ($parameter->isVariadic()) {
			throw self::invalid($class, $parameter, 'is a variadic option; use an array option instead');
		}

		$type = $parameter->getType();
		$list = $type instanceof ReflectionNamedType && $type->getName() === 'array';

		[$valueType, $enum] = $list ? [ValueType::String, null] : self::valueType($class, $parameter);

		if (! $parameter->isDefaultValueAvailable() && ! $parameter->allowsNull() && ! $list && $valueType !== ValueType::Bool) {
			throw self::invalid($class, $parameter, 'is an option without a default; give it one or make it nullable');
		}

		$default = match (true) {
			$parameter->isDefaultValueAvailable() => $parameter->getDefaultValue(),
			$list                                 => [],
			$valueType === ValueType::Bool        => false,
			default                               => null
		};

		return new OptionDefinition(
			name: $attribute->name ?? self::kebab($parameter->getName()),
			parameter: $parameter->getName(),
			type: $valueType,
			description: $attribute->description,
			short: $attribute->short,
			list: $list,
			default: $default,
			enum: $enum
		);
	}

	/**
	 * Reads a parameter's value type.
	 *
	 * @return array{ValueType, ?class-string<BackedEnum>}
	 * @throws InvalidCommand
	 */
	private static function valueType(string $class, ReflectionParameter $parameter): array
	{
		$type = $parameter->getType();

		if (! $type instanceof ReflectionNamedType) {
			throw self::invalid($class, $parameter, 'must have a single named type');
		}

		$name = $type->getName();

		if (is_subclass_of($name, BackedEnum::class)) {
			return [ValueType::Enum, $name];
		}

		return match ($name) {
			'string' => [ValueType::String, null],
			'int'    => [ValueType::Int, null],
			'float'  => [ValueType::Float, null],
			'bool'   => [ValueType::Bool, null],
			default  => throw self::invalid($class, $parameter, sprintf('has unsupported type "%s"', $name))
		};
	}

	/**
	 * Converts a camelCase parameter name to kebab-case.
	 */
	private static function kebab(string $name): string
	{
		return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
	}

	/**
	 * Builds an exception about a parameter.
	 */
	private static function invalid(string $class, ReflectionParameter $parameter, string $problem): InvalidCommand
	{
		return new InvalidCommand(sprintf('%s::__invoke() parameter $%s %s.', $class, $parameter->getName(), $problem));
	}
}
