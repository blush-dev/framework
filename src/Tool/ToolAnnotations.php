<?php

/**
 * Tool annotations.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

/**
 * What a tool says of itself (D-753, D-757): whether it only reads,
 * whether a write can destroy something, whether calling it again with
 * the same input changes nothing more, and, for a tool that writes,
 * whether it only ever writes drafts, so a token that may write drafts
 * may use it. Clients use the first three to ask the person before a
 * write; they're hints, never the check, which is the tool's capability.
 */
final readonly class ToolAnnotations
{
	public function __construct(
		public bool $readOnly = true,
		public bool $destructive = false,
		public bool $idempotent = true,
		public bool $draftsSafe = false
	) {}

	/**
	 * A tool that only reads.
	 */
	public static function reads(): self
	{
		return new self();
	}

	/**
	 * A tool that writes.
	 */
	public static function writes(bool $destructive = false, bool $idempotent = false, bool $draftsSafe = false): self
	{
		return new self(false, $destructive, $idempotent, $draftsSafe);
	}

	/**
	 * The annotations as MCP sends them.
	 *
	 * @return array<string, bool>
	 */
	public function toArray(): array
	{
		return [
			'readOnlyHint'    => $this->readOnly,
			'destructiveHint' => ! $this->readOnly && $this->destructive,
			'idempotentHint'  => $this->idempotent,
			'openWorldHint'   => false
		];
	}
}
