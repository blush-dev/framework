<?php

/**
 * Admin field types controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\FieldRegistry;
use Blush\Http\Response;

/**
 * Answers `GET {path}/api/fields/types` (D-337): the field types the site
 * has, built-in and from extensions, so the admin's field definition
 * editor offers them all without a list of its own. Each type has its
 * `type` (the registry key definitions use), a `label` for people (its
 * key when the class gives none), a `description`, the `controls` it can
 * be edited with (`{"value", "label"}`, the first being its default), and
 * its own definition keys as JSON Schemas (`options`, by key, from
 * `Field::definitionSchema()`, without `default`). `controls` lists every
 * `Control` the admin draws, with its label.
 */
final readonly class FieldTypesController
{
	public function __construct(private FieldRegistry $registry)
	{}

	public function __invoke(): ResponseInterface
	{
		$types = [];

		foreach ($this->registry->all() as $key => $class) {
			/** @var class-string<Field> $class */
			$types[] = [
				'type'        => $key,
				'label'       => $class::typeLabel() === '' ? $key : $class::typeLabel(),
				'description' => $class::typeDescription(),
				'controls'    => array_map(self::control(...), $class::controls()),
				'options'     => (object) array_diff_key($class::definitionSchema(['type' => 'object']), ['default' => true])
			];
		}

		return Response::json(
			['types' => $types, 'controls' => array_map(self::control(...), Control::cases())],
			headers: ['Cache-Control' => 'no-store']
		);
	}

	/**
	 * Describes a control.
	 *
	 * @return array{value: string, label: string}
	 */
	private static function control(Control $control): array
	{
		return ['value' => $control->value, 'label' => $control->label()];
	}
}
