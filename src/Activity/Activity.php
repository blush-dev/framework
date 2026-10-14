<?php

/**
 * Activity.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

use NoDiscard;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * One change in the activity log (D-758): when, by which account and
 * how (`via`, an app's or token's name as `client`, and its `token`),
 * what it did (`action`) to which entry or media file (`subject`, by
 * id; `title` as it was then), a line saying so (`summary`), and, for a
 * change that can be undone, what it replaced (`before`) and the version
 * it wrote (`revision`), so Undo puts it back only while nothing has
 * changed since. A change that undid another names it (`undoes`), and
 * the other names it back (`undone`).
 *
 * Its summary is best written with `t()` (D-836): it's kept as its
 * message and read back as a `Text`, shown in the reader's language. One
 * kept as text is shown as it is.
 */
final readonly class Activity
{
	/**
	 * @param ?array<string, mixed> $before
	 */
	public function __construct(
		public string $id,
		public int $time,
		public string $account,
		public Via $via,
		public ActivityAction $action,
		public string $subject,
		public string $title = '',
		public string|Text $summary = '',
		public ?string $client = null,
		public ?string $token = null,
		public ?array $before = null,
		public ?string $revision = null,
		public ?string $undoes = null,
		public ?string $undone = null
	) {}

	/**
	 * Whether Undo is offered: it replaced something, which is still
	 * kept, and nothing has undone it yet.
	 */
	public function canUndo(): bool
	{
		return $this->action->isUndoable() && $this->before !== null && $this->undone === null;
	}

	/**
	 * Returns a copy undone by another change.
	 */
	#[NoDiscard]
	public function withUndone(string $by): self
	{
		return clone($this, ['undone' => $by]);
	}

	/**
	 * Returns a copy without what it replaced, once that's no longer kept.
	 */
	#[NoDiscard]
	public function withoutBefore(): self
	{
		return clone($this, ['before' => null]);
	}

	/**
	 * Builds a change from its stored array and its record's id, its
	 * summary read back with `$messages`.
	 *
	 * @param  array<mixed> $data
	 * @throws ActivityException When the data isn't a change.
	 */
	public static function fromArray(array $data, string $id, Messages $messages): self
	{
		$via    = is_string($data['via'] ?? null) ? Via::tryFrom($data['via']) : null;
		$action = is_string($data['action'] ?? null) ? ActivityAction::tryFrom($data['action']) : null;
		$before = $data['before'] ?? null;

		if ($via === null || $action === null || ! is_int($data['time'] ?? null) || ! is_string($data['account'] ?? null) || ! is_string($data['subject'] ?? null)) {
			throw new ActivityException((string) $messages->t('activity.activity.damaged', 'The activity "{id}" is damaged.', id: $id));
		}

		$text = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

		/** @var ?array<string, mixed> $before */
		$before = is_array($before) ? $before : null;

		return new self(
			id: $id,
			time: $data['time'],
			account: $data['account'],
			via: $via,
			action: $action,
			subject: $data['subject'],
			title: $text('title') ?? '',
			summary: $text('summary') ?? $messages->restore($data['summary'] ?? null) ?? '',
			client: $text('client'),
			token: $text('token'),
			before: $before,
			revision: $text('revision'),
			undoes: $text('undoes'),
			undone: $text('undone')
		);
	}

	/**
	 * Returns the change as its stored array, without its id, which is
	 * the record's.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'time'     => $this->time,
			'account'  => $this->account,
			'via'      => $this->via->value,
			'client'   => $this->client,
			'token'    => $this->token,
			'action'   => $this->action->value,
			'subject'  => $this->subject,
			'title'    => $this->title,
			'summary'  => $this->summary instanceof Text ? $this->summary->toArray() : $this->summary,
			'before'   => $this->before,
			'revision' => $this->revision,
			'undoes'   => $this->undoes,
			'undone'   => $this->undone
		];
	}
}
