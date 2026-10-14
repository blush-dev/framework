<?php

/**
 * Job record.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use NoDiscard;
use Blush\Support\Uuid;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * One job, as it's kept (D-621): a record keyed by its id, as every
 * stored thing is (D-606), so a database can keep it as well as a file.
 *
 * - `id` is a UUIDv7, so jobs sort by when they were queued.
 * - `job` is the registered key of the work (`core/media-index`), never a
 *   class name, and `data` is what it works on: ids and scalars only.
 * - `attempts` counts the runs that threw; `available` is the time it may
 *   run (later after a failure); `queued`, `started`, and `finished` are
 *   Unix times.
 * - `progress` (0 to 100), `message`, and `details` are what the job last
 *   said about itself; `error` is its last failure's message; `result` is
 *   plain data a finished job hands whoever follows it (D-624). A
 *   message, a detail, or an error written with `t()` is kept as its
 *   message (`Text::toArray()`) and read back as a `Text`, shown in the
 *   reader's language (D-836); one kept as text is shown as it is.
 * - `account` is the id of the account that queued it (D-668), or `null` for the
 *   system (the scheduler); `unique` keeps a second copy from being
 *   queued while one waits or runs.
 */
final readonly class JobRecord
{
	/**
	 * @param array<string, mixed> $data
	 * @param list<string|Text>    $details
	 * @param array<string, mixed> $result
	 */
	public function __construct(
		public string $id,
		public string $job,
		public array $data = [],
		public JobStatus $status = JobStatus::Queued,
		public int $attempts = 0,
		public int $queued = 0,
		public int $available = 0,
		public ?int $started = null,
		public ?int $finished = null,
		public ?int $progress = null,
		public string|Text $message = '',
		public array $details = [],
		public string|Text|null $error = null,
		public ?string $account = null,
		public ?string $unique = null,
		public array $result = []
	) {}

	/**
	 * Returns a copy with other values.
	 *
	 * @param array<string, mixed> $values
	 */
	#[NoDiscard]
	public function with(array $values): self
	{
		return clone($this, $values);
	}

	/**
	 * Returns the record as plain data.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id'        => $this->id,
			'job'       => $this->job,
			'data'      => $this->data,
			'status'    => $this->status->value,
			'attempts'  => $this->attempts,
			'queued'    => $this->queued,
			'available' => $this->available,
			'started'   => $this->started,
			'finished'  => $this->finished,
			'progress'  => $this->progress,
			'message'   => self::store($this->message),
			'details'   => array_map(self::store(...), $this->details),
			'error'     => $this->error === null ? null : self::store($this->error),
			'account'   => $this->account,
			'unique'    => $this->unique,
			'result'    => $this->result
		];
	}

	/**
	 * Builds a record from `toArray()`'s data, or returns `null` when it
	 * isn't one. Its stored messages are read back with `$messages`.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data, Messages $messages): ?self
	{
		$status = is_string($data['status'] ?? null) ? JobStatus::tryFrom($data['status']) : null;
		$job    = $data['job'] ?? null;
		$values = $data['data'] ?? [];

		if (! Uuid::isValid($data['id'] ?? null) || ! is_string($job) || $status === null || ! is_array($values)) {
			return null;
		}

		/** @var string $id */
		$id      = $data['id'];
		$read    = static fn (mixed $value): string|Text|null => is_string($value) ? $value : $messages->restore($value);
		$details = is_array($data['details'] ?? null) ? array_values(array_filter(array_map($read, $data['details']), static fn (string|Text|null $detail): bool => $detail !== null)) : [];

		$result = is_array($data['result'] ?? null) ? $data['result'] : [];

		/**
		 * @var array<string, mixed> $values
		 * @var array<string, mixed> $result
		 */
		return new self(
			id: $id,
			job: $job,
			data: $values,
			status: $status,
			attempts: self::int($data['attempts'] ?? 0),
			queued: self::int($data['queued'] ?? 0),
			available: self::int($data['available'] ?? 0),
			started: self::optionalInt($data['started'] ?? null),
			finished: self::optionalInt($data['finished'] ?? null),
			progress: self::optionalInt($data['progress'] ?? null),
			message: $read($data['message'] ?? null) ?? '',
			details: $details,
			error: $read($data['error'] ?? null),
			account: is_string($data['account'] ?? null) ? $data['account'] : null,
			unique: is_string($data['unique'] ?? null) ? $data['unique'] : null,
			result: $result
		);
	}

	/**
	 * Whether data holds only plain values (strings, numbers, booleans,
	 * null, and arrays of them), as a job's must.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function isPlain(array $data): bool
	{
		return array_all($data, static fn (mixed $value): bool => $value === null || is_scalar($value) || (is_array($value) && self::isPlain($value)));
	}

	/**
	 * Returns a message to keep: a `Text` as its message, text as it is.
	 *
	 * @return string|array<string, mixed>
	 */
	private static function store(string|Text $message): string|array
	{
		return $message instanceof Text ? $message->toArray() : $message;
	}

	/**
	 * Returns a value as an integer, or zero.
	 */
	private static function int(mixed $value): int
	{
		return is_int($value) ? $value : 0;
	}

	/**
	 * Returns a value as an integer, or `null`.
	 */
	private static function optionalInt(mixed $value): ?int
	{
		return is_int($value) ? $value : null;
	}
}
