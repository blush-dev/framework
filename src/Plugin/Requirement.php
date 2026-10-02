<?php

/**
 * Plugin requirement.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

/**
 * One of a plugin's `requires`, checked against the site (D-385): what
 * it names, its constraint, its kind, whether it's met, and a note on
 * what the site has (`this site runs 8.5.1`, `isn't installed`, `is
 * turned off`). A plugin requirement has the required plugin's `label`
 * when it's installed.
 */
final readonly class Requirement
{
	public function __construct(
		public string $name,
		public string $constraint,
		public RequirementKind $kind,
		public bool $met,
		public string $note = '',
		public string $label = ''
	) {}

	/**
	 * Says what's needed and what the site has, for messages:
	 * `Blush ^3.0 (this site runs 2.0.0)`, `the acme/crm plugin (isn't
	 * installed)`.
	 */
	public function describe(): string
	{
		$constraint = $this->constraint === '*' ? '' : " {$this->constraint}";
		$plugin     = $this->label !== '' ? $this->label : "the {$this->name} plugin";

		$what = match ($this->kind) {
			RequirementKind::Blush     => "Blush {$this->constraint}",
			RequirementKind::Php       => "PHP {$this->constraint}",
			RequirementKind::Extension => 'the PHP extension ' . substr($this->name, 4) . $constraint,
			RequirementKind::Plugin    => $plugin . $constraint,
			RequirementKind::Unknown   => "{$this->name} {$this->constraint}"
		};

		return trim($what) . ($this->note === '' ? '' : " ({$this->note})");
	}

	/**
	 * The requirement as the admin shows it.
	 *
	 * @return array{name: string, constraint: string, kind: string, met: bool, note: string, label: string}
	 */
	public function toArray(): array
	{
		return [
			'name'       => $this->name,
			'constraint' => $this->constraint,
			'kind'       => $this->kind->value,
			'met'        => $this->met,
			'note'       => $this->note,
			'label'      => $this->label
		];
	}
}
