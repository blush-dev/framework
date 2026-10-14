<?php

/**
 * Field controls controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use Psr\Http\Message\ResponseInterface;
use Blush\Field\ControlFactory;
use Blush\Field\Field;
use Blush\Field\FieldRegistry;
use Blush\Http\Response;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Answers `GET {path}/api/fields/controls` (D-766): the catalog the
 * admin's field editors are built from.
 *
 * - **`controls`**, every control in the picker's order: its `value`
 *   (the key definitions use), `label`, `description`, the picker
 *   `section` it's offered in (`{"value", "label"}`, or `null` for one
 *   the picker doesn't offer), the value `type` it stores (`null` for
 *   one that edits several), its `shape` (controls of one shape store the
 *   same value, so a saved field switches only among them, D-768), the
 *   type options it `implies`, and its own
 *   definition keys as JSON Schemas (`options`).
 * - **`types`**, every field type, built in and from extensions: its
 *   `type`, `label` (its key when the class gives none), `description`,
 *   its own definition keys as JSON Schemas (`options`, without
 *   `default`), and the `controls` that store it without implying
 *   options (`{"value", "label"}`), the ones a definition can name over
 *   the type's default.
 */
final readonly class FieldControlsController
{
	public function __construct(
		private FieldRegistry $registry,
		private ControlFactory $controls,
		private Messages $messages
	) {}

	public function __invoke(): ResponseInterface
	{
		$controls = [];

		foreach ($this->controls->all() as $class) {
			$section    = $class::section();
			$controls[] = [
				'value'       => $class::name(),
				'label'       => $class::label($this->messages),
				'description' => $class::description($this->messages),
				'section'     => $section === null ? null : ['value' => $section->value, 'label' => $section->label($this->messages)],
				'type'        => $class::type(),
				'shape'       => $class::shape(),
				'implies'     => (object) $class::implies(),
				'options'     => (object) $class::definitionSchema()
			];
		}

		$types = [];

		foreach ($this->registry->all() as $key => $class) {
			/** @var class-string<Field> $class */
			$types[] = [
				'type'        => $key,
				'label'       => $class::typeLabel($this->messages) === '' ? $key : $class::typeLabel($this->messages),
				'description' => $class::typeDescription($this->messages),
				'controls'    => $this->storing($key),
				'options'     => (object) array_diff_key($class::definitionSchema(['type' => 'object']), ['default' => true])
			];
		}

		return Response::json(['controls' => $controls, 'types' => $types], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns the controls that store a type without implying options.
	 *
	 * @return list<array{value: string, label: string|Text}>
	 */
	private function storing(string $type): array
	{
		$found = array_filter(
			$this->controls->all(),
			static fn (string $class): bool => $class::implies() === [] && in_array($type, $class::stores(), true)
		);

		return array_values(array_map(fn (string $class): array => ['value' => $class::name(), 'label' => $class::label($this->messages)], $found));
	}
}
