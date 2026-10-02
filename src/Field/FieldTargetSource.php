<?php

/**
 * Field target source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * Supplies the places field sets can attach to, of one kind (D-337):
 * content types, kinds of media file. Each subsystem that takes sets
 * tags its source with `FieldTargetSource::TAG`; an extension with places
 * of its own can too.
 */
interface FieldTargetSource
{
	/**
	 * The container tag for target sources.
	 */
	public const string TAG = 'field.targets';

	/**
	 * Returns the kind of target, the part of a key before the colon
	 * (`type`).
	 */
	public function kind(): string;

	/**
	 * Returns the kind's name for people, heading its targets in the
	 * admin ("Content types").
	 */
	public function label(): string;

	/**
	 * Returns the targets.
	 *
	 * @return iterable<FieldTarget>
	 */
	public function fieldTargets(): iterable;

	/**
	 * Returns the slots the kind's places offer sets (D-347), the first
	 * being the default: where a set naming none, or one the kind doesn't
	 * have, goes. Every kind has at least one.
	 *
	 * @return non-empty-list<FieldSlot>
	 */
	public function slots(): array;

	/**
	 * Returns why sets don't fit the kind's targets taken together, by set
	 * name, beyond each target's own schema (`FieldSets::schemaFor()`):
	 * for a kind whose places share one store, such as the settings
	 * screens, two sets using one name on different targets. Most kinds
	 * have none.
	 *
	 * @return array<string, string>
	 */
	public function conflicts(FieldSets $sets): array;
}
