<?php

/**
 * Text box control base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Controls;

use Override;
use Blush\Field\Control;
use Blush\Field\Definition;

/**
 * A control that puts a box in front of someone, and so can say what goes
 * in it while it's empty (`placeholder`, never saved as a value).
 */
abstract class TextBox extends Control
{
	final public function __construct(public readonly string $placeholder = '')
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		return new static(new Definition($data, sprintf('Field "%s"', is_string($data['name'] ?? null) ? $data['name'] : ''))->string('placeholder'));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function options(): array
	{
		return $this->placeholder === '' ? [] : ['placeholder' => $this->placeholder];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(): array
	{
		return ['placeholder' => ['type' => 'string', 'description' => 'A prompt shown in the empty box. Never saved.']];
	}
}
