<?php

/**
 * Transition.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Blush\Translation\Text;

/**
 * A move from some statuses to another in a workflow (D-735, D-851):
 * Publish, Unpublish, or a site's Submit for Review. Saving without
 * changing status isn't one.
 *
 * Making one needs edit access to the entry, its capability
 * (`content.{type}.transition.{name}`, or `content.*.transition.{name}`
 * for every type) unless it has none of its own (`capability: false`),
 * and, into or out of a live status, publishing the entry (D-734).
 *
 *     new Transition('submit', 'Submit for Review', from: ['draft'], to: 'acme_in_review');
 */
final readonly class Transition
{
	/**
	 * What its button says, in Title Case.
	 */
	public string|Text $label;

	/**
	 * @param string       $name       Lowercase letters, digits, and underscores, unique in its workflow.
	 * @param string|Text  $label      What its button says ("Submit for Review"); `''` for one made from the name.
	 * @param list<string> $from       The statuses it moves from; none for any of the workflow's.
	 * @param string       $to         The status it moves to.
	 * @param bool         $capability Whether it needs a capability of its own; `false` for one `publish` alone allows.
	 * @throws InvalidWorkflow When the name isn't one.
	 */
	public function __construct(
		public string $name,
		string|Text $label = '',
		public array $from = [],
		public string $to = '',
		public bool $capability = true
	) {
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidWorkflow(sprintf('A transition name is lowercase letters, digits, and underscores, starting with a letter; "%s" isn\'t.', $name));
		}

		$this->label = (string) $label === '' ? ucwords(str_replace('_', ' ', $name)) : $label;
	}

	/**
	 * Returns whether it moves from a status.
	 */
	public function leaves(string $status): bool
	{
		return $this->from === [] ? $status !== $this->to : in_array($status, $this->from, true);
	}

	/**
	 * Returns the name of its capability on a type (or `*` for every
	 * type).
	 */
	public function capabilityOn(string $type): string
	{
		return self::capabilityName($type, $this->name);
	}

	/**
	 * Returns the name of a transition's capability on a type (or `*`).
	 */
	public static function capabilityName(string $type, string $name): string
	{
		return "content.{$type}.transition.{$name}";
	}

	/**
	 * Splits a transition capability into its type (or `*`) and
	 * transition, or returns `null` for any other capability.
	 *
	 * @return ?array{string, string}
	 */
	public static function parse(string $capability): ?array
	{
		return preg_match('/^content\.([a-z][a-z0-9_]*|\*)\.transition\.([a-z][a-z0-9_]*)$/', $capability, $match) === 1 ? [$match[1], $match[2]] : null;
	}

	/**
	 * Returns it as plain data, for the admin and the API.
	 *
	 * @return array{name: string, label: string, from: list<string>, to: string, capability: bool}
	 */
	public function toArray(): array
	{
		return [
			'name'       => $this->name,
			'label'      => (string) $this->label,
			'from'       => $this->from,
			'to'         => $this->to,
			'capability' => $this->capability
		];
	}
}
