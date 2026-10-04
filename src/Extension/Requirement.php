<?php

/**
 * Extension requirement.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * One of an extension's `require`, checked against the site (D-385,
 * D-431): what it names, its constraint, its kind, whether it's met, and
 * a note on what the site has (`this site runs 8.5.1`, `isn't
 * installed`, `is turned off`). A requirement of another extension has
 * that extension's `label` when it's installed.
 *
 * A `conflict` (D-435) is one too: what it names and the versions it
 * can't run with, met when the site doesn't have one of them on. So is
 * each package it `replace`s (D-436), a conflict at any version, met
 * when that package isn't on. A requirement met by an extension that
 * replaces or provides what it names (D-439), or a conflict with one,
 * says which one (`metBy`).
 */
final readonly class Requirement
{
	public function __construct(
		public string $name,
		public string $constraint,
		public RequirementKind $kind,
		public bool $met,
		public string $note = '',
		public string $label = '',
		public bool $conflict = false,
		public bool $replace = false,
		public string $metBy = ''
	) {}

	/**
	 * Says what's needed and what the site has, for messages:
	 * `Blush ^3.0 (this site runs 2.0.0)`, `Shop ^2.0 (is turned off)`,
	 * `acme/crm (isn't installed)`.
	 */
	public function describe(): string
	{
		$constraint = $this->constraint === '*' || $this->replace ? '' : " {$this->constraint}";
		$extension  = $this->label !== '' ? $this->label : $this->name;

		$what = match ($this->kind) {
			RequirementKind::Blush     => "Blush {$this->constraint}",
			RequirementKind::Php       => "PHP {$this->constraint}",
			RequirementKind::Extension => 'the PHP extension ' . substr($this->name, 4) . $constraint,
			RequirementKind::Plugin,
			RequirementKind::Theme,
			RequirementKind::IconPack  => $extension . $constraint,
			RequirementKind::Library,
			RequirementKind::Composer,
			RequirementKind::Missing   => $this->name . $constraint,
			RequirementKind::Unknown   => "{$this->name} {$this->constraint}"
		};

		return trim($what) . ($this->note === '' ? '' : " ({$this->note})");
	}

	/**
	 * The requirement as the admin shows it.
	 *
	 * @return array{name: string, constraint: string, kind: string, met: bool, note: string, label: string, metBy: string}
	 */
	public function toArray(): array
	{
		return [
			'name'       => $this->name,
			'constraint' => $this->constraint,
			'kind'       => $this->kind->value,
			'met'        => $this->met,
			'note'       => $this->note,
			'label'      => $this->label,
			'metBy' => $this->metBy
		];
	}
}
