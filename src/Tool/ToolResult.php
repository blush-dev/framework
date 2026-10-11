<?php

/**
 * Tool result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

use Blush\Job\JobRecord;

/**
 * What a tool answers (D-753, D-761): its data, in the shape its output
 * schema gives, a short line of text for people (the Tools screen's
 * message, the command line's), and optional details, a line each (files
 * that couldn't be indexed). A tool run in the background answers with
 * its job (`queued()`), which the admin follows to its end.
 */
final readonly class ToolResult
{
	/**
	 * @param array<string, mixed> $data
	 * @param list<string>         $details
	 */
	public function __construct(
		public array $data = [],
		public ?string $text = null,
		public array $details = [],
		public ?string $job = null
	) {}

	/**
	 * A tool that did what it was asked, said in words, with no data.
	 *
	 * @param list<string> $details
	 */
	public static function done(string $text, array $details = []): self
	{
		return new self(text: $text, details: $details);
	}

	/**
	 * A tool queued to run in the background.
	 */
	public static function queued(JobRecord $job): self
	{
		return new self(['job' => $job->id, 'status' => $job->status->value], 'Queued; it runs in the background.', job: $job->id);
	}
}
