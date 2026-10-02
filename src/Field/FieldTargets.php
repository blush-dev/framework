<?php

/**
 * Field targets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Blush\Container\Container;

/**
 * Every place field sets can attach to on the site (D-337), from the
 * tagged `FieldTargetSource`s, by kind and by key. Read when they're
 * first needed: lint, the admin's set screens, and the set writer's
 * checks.
 */
final class FieldTargets
{
	/**
	 * @var ?array<string, FieldTargetSource>
	 */
	private ?array $sources = null;

	/**
	 * @var ?array<string, FieldTarget>
	 */
	private ?array $targets = null;

	public function __construct(private readonly Container $container)
	{}

	/**
	 * Returns the sources, keyed by kind.
	 *
	 * @return array<string, FieldTargetSource>
	 * @throws InvalidSchema When a tagged service isn't a source, or two have one kind.
	 */
	public function sources(): array
	{
		if ($this->sources !== null) {
			return $this->sources;
		}

		$sources = [];

		foreach ($this->container->tagged(FieldTargetSource::TAG) as $source) {
			if (! $source instanceof FieldTargetSource) {
				throw new InvalidSchema(sprintf('Services tagged "%s" must implement %s; %s does not.', FieldTargetSource::TAG, FieldTargetSource::class, get_debug_type($source)));
			}

			if (isset($sources[$source->kind()])) {
				throw new InvalidSchema(sprintf('Two field target sources have the "%s" kind.', $source->kind()));
			}

			$sources[$source->kind()] = $source;
		}

		return $this->sources = $sources;
	}

	/**
	 * Returns every target, keyed by key, kind by kind.
	 *
	 * @return array<string, FieldTarget>
	 * @throws InvalidSchema
	 */
	public function all(): array
	{
		if ($this->targets !== null) {
			return $this->targets;
		}

		$targets = [];

		foreach ($this->sources() as $source) {
			foreach ($source->fieldTargets() as $target) {
				$targets[$target->key()] = $target;
			}
		}

		return $this->targets = $targets;
	}

	/**
	 * Returns a target by key, if the site has it.
	 *
	 * @throws InvalidSchema
	 */
	public function find(string $key): ?FieldTarget
	{
		return $this->all()[$key] ?? null;
	}

	/**
	 * Returns why sets don't fit the targets, by set name: each target's
	 * clashes (`FieldSets::clashes()`), then each kind's conflicts across
	 * its targets (`FieldTargetSource::conflicts()`).
	 *
	 * @return array<string, list<string>>
	 * @throws InvalidSchema When a target's own fields don't fit.
	 */
	public function problems(FieldSets $sets): array
	{
		$problems = [];

		foreach ($this->all() as $target) {
			foreach ($sets->clashes($target) as $name => $message) {
				$problems[$name][] = $message;
			}
		}

		foreach ($this->sources() as $source) {
			foreach ($source->conflicts($sets) as $name => $message) {
				$problems[$name][] = $message;
			}
		}

		return $problems;
	}

	/**
	 * Returns the slots a kind of place offers, the first being its
	 * default, or none for a kind the site doesn't have.
	 *
	 * @return list<FieldSlot>
	 * @throws InvalidSchema
	 */
	public function slots(string $kind): array
	{
		return ($this->sources()[$kind] ?? null)?->slots() ?? [];
	}

	/**
	 * Returns the slot a set is shown in (D-347): the one it names, when
	 * its kind offers it, or else the kind's default; `null` for a set
	 * with no targets the site has a kind for.
	 *
	 * @throws InvalidSchema
	 */
	public function slotFor(FieldSet $set): ?FieldSlot
	{
		$slots = $this->slots($set->kind() ?? '');

		return array_find($slots, static fn (FieldSlot $slot): bool => $slot->name === $set->slot) ?? $slots[0] ?? null;
	}

	/**
	 * Returns whether the site has a kind of target.
	 *
	 * @throws InvalidSchema
	 */
	public function hasKind(string $kind): bool
	{
		return isset($this->sources()[$kind]);
	}
}
