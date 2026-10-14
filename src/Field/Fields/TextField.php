<?php

/**
 * Text field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Fields;

use Override;
use Uri\Rfc3986\Uri;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;
use Blush\Field\TextFormat;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * A single line or block of plain text. Numbers are accepted and kept as
 * text, since YAML reads `title: 1984` as an integer. A `format` says what
 * the text must look like (a web address, an email address, or an icon
 * name), so the link, email, and icon controls' checks are the type's
 * (D-769).
 */
final class TextField extends Field
{
	public function __construct(string $name = '', public readonly ?TextFormat $format = null)
	{
		$this->name = $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'text';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(Messages $messages): Text
	{
		return $messages->t('field.text_field.type_label', 'Text');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(Messages $messages): Text
	{
		return $messages->t('field.text_field.type_description', 'A line of text.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_int($value) || is_float($value)) {
			$value = (string) $value;
		}

		if (! is_string($value)) {
			throw $this->invalid($context->messages->t('field.text_field.invalid', 'must be text, not {value}.', value: self::describe($value, $context->messages)));
		}

		if ($this->format !== null && ! self::matches(trim($value), $this->format)) {
			throw $this->invalid($context->messages->t('field.text_field.format', 'must be {format}, not "{value}".', format: $this->format->label($context->messages), value: $value));
		}

		return $this->format === null ? $value : trim($value);
	}

	/**
	 * Returns whether text is in a format: a web address with `http` or
	 * `https` and a host, or a path on the site; an email address; or an
	 * icon name, which has no spaces.
	 */
	private static function matches(string $value, TextFormat $format): bool
	{
		return match ($format) {
			TextFormat::Url   => (str_starts_with($value, '/') && ! str_starts_with($value, '//')) || self::isWebAddress($value),
			TextFormat::Email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
			TextFormat::Icon  => preg_match('/^\S+$/', $value) === 1
		};
	}

	/**
	 * Returns whether text is an `http` or `https` address with a host.
	 */
	private static function isWebAddress(string $value): bool
	{
		$uri = Uri::parse($value);

		return $uri !== null && in_array(strtolower($uri->getScheme() ?? ''), ['http', 'https'], true) && ($uri->getHost() ?? '') !== '';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		return ['type' => ['string', 'number']];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);
		$format     = $definition->nullableString('format');
		$known      = $format === null ? null : TextFormat::tryFrom($format);

		if ($format !== null && $known === null) {
			throw new InvalidSchema(sprintf(
				'Field "%s" has an unknown format "%s"; formats are %s.',
				$definition->string('name'),
				$format,
				implode(', ', array_column(TextFormat::cases(), 'value'))
			));
		}

		return self::withShared(new static($definition->string('name'), $known), $definition);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return [
			'format' => ['enum' => array_column(TextFormat::cases(), 'value'), 'description' => 'What the text must look like: a web address, an email address, or an icon name.']
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['format' => $this->format?->value];
	}
}
