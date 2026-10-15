<?php

/**
 * Status.
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
 * Where an entry (or, later, a response) is in its life (D-732 to
 * D-735, D-850): a name written in front matter (`status: draft`), with
 * a label, a plural for list tabs, and what it does. Statuses are one
 * list per subject, registered by core and extensions (`StatusSource`),
 * so a name means the same thing in every file.
 *
 * - **Live** ones are on the site once their `published` time comes.
 * - **Active** ones are what "any status" finds: lists, counts, and
 *   queries for every status leave the others out unless they ask
 *   for them by name.
 *
 * Core's own names (`CORE`) are never registered: `trash` is set by
 * Move to Trash, and `scheduled` is worked out from a live status's
 * `published` date. They're listed by `Statuses` so tabs and labels
 * work. Visibility is separate (D-082).
 *
 *     new Status('in_review', 'In Review', 'In Review', tone: Tone::Warn);
 */
final readonly class Status
{
	/**
	 * Core's draft status: not on the site.
	 */
	public const string DRAFT = 'draft';

	/**
	 * Core's published status: on the site once its time comes. A file
	 * without a status has it.
	 */
	public const string PUBLISHED = 'published';

	/**
	 * Core's trash status (D-484), set by Move to Trash.
	 */
	public const string TRASH = 'trash';

	/**
	 * Core's scheduled status: a live status whose `published` time is
	 * still to come.
	 */
	public const string SCHEDULED = 'scheduled';

	/**
	 * The names only core sets, which can't be registered.
	 *
	 * @var list<string>
	 */
	public const array CORE = [self::TRASH, self::SCHEDULED];

	/**
	 * What people call it.
	 */
	public string|Text $label;

	/**
	 * What a list tab of them is called; the label when not given.
	 */
	public string|Text $plural;

	/**
	 * @param string      $name    How it's written in front matter, in lowercase letters, digits, and underscores.
	 * @param string|Text $label   What people call it ("In Review").
	 * @param string|Text $plural  What a list tab of them is called; `''` for the label.
	 * @param bool        $live    Whether entries with it are on the site.
	 * @param bool        $active  Whether "any status" finds it.
	 * @param Subject     $subject What it's for.
	 * @param Tone        $tone    How the admin draws its pill.
	 * @param bool        $known   Whether it's registered; `false` for one only a file names.
	 * @throws InvalidStatus When a registered status's name isn't one.
	 */
	public function __construct(
		public string $name,
		string|Text $label = '',
		string|Text $plural = '',
		public bool $live = false,
		public bool $active = true,
		public Subject $subject = Subject::Entries,
		public Tone $tone = Tone::Neutral,
		public bool $known = true
	) {
		if ($known && preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidStatus(sprintf('A status name is lowercase letters, digits, and underscores, starting with a letter; "%s" isn\'t.', $name));
		}

		$this->label  = (string) $label === '' ? ucfirst(str_replace('_', ' ', $name)) : $label;
		$this->plural = (string) $plural === '' ? $this->label : $plural;
	}

	/**
	 * Returns a definition for a status a file names but nothing
	 * registers (a plugin turned off): not live, active so it can be
	 * found and fixed, and named by itself.
	 */
	public static function unknown(string $name, Subject $subject = Subject::Entries): self
	{
		return new self($name, $name, subject: $subject, known: false);
	}

	/**
	 * Returns whether it's the status of a name.
	 */
	public function is(string $name): bool
	{
		return $this->name === $name;
	}

	/**
	 * Returns whether it's one of core's own (`CORE`).
	 */
	public function isCore(): bool
	{
		return in_array($this->name, self::CORE, true);
	}

	/**
	 * Returns it as plain data, for the admin and the API.
	 *
	 * @return array{name: string, label: string, plural: string, live: bool, active: bool, subject: string, tone: string, known: bool}
	 */
	public function toArray(): array
	{
		return [
			'name'    => $this->name,
			'label'   => (string) $this->label,
			'plural'  => (string) $this->plural,
			'live'    => $this->live,
			'active'  => $this->active,
			'subject' => $this->subject->value,
			'tone'    => $this->tone->value,
			'known'   => $this->known
		];
	}
}
