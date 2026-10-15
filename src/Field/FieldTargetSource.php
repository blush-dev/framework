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

use Blush\Translation\Text;

/**
 * Supplies the places fields go on, of one kind (D-337): content types,
 * kinds of media file, Settings screens. Each subsystem with places tags
 * its source with `FieldTargetSource::TAG`; an extension with places of
 * its own can too.
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
	 * admin ("Content types"), written with `t()` (D-836).
	 */
	public function label(): string|Text;

	/**
	 * Returns the targets.
	 *
	 * @return iterable<FieldTarget>
	 */
	public function fieldTargets(): iterable;

	/**
	 * Returns the slots the kind's places offer (D-347), the places on
	 * their screens that code names, the first being the default. Every
	 * kind has at least one.
	 *
	 * @return non-empty-list<FieldSlot>
	 */
	public function slots(): array;
}
