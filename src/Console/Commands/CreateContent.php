<?php

/**
 * Content scaffolding command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Psr\Clock\ClockInterface;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Core\Paths;
use Blush\Support\Slug;

/**
 * Creates a Markdown entry in a type's folder, then refreshes the index.
 * The file is named after the slug (from the title unless `--slug` is
 * given). Types with date archives get jtcom's `Y-m-d.slug.md` file names
 * and a `published` date; `--draft` marks the entry as a draft. Existing
 * files are never overwritten.
 */
#[Command('content:new', 'Create a new entry.')]
final readonly class CreateContent
{
	public function __construct(
		private ContentTypes $types,
		private Paths $paths,
		private ClockInterface $clock,
		private ContentWriter $writer
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Argument('The content type.')] string $type,
		#[Argument('The entry title.')] string $title,
		#[Option('The slug; defaults to one made from the title.')] ?string $slug = null,
		#[Option('Create the entry as a draft.')] bool $draft = false
	): ExitCode {
		$contentType = $this->types->find($type)
			?? throw new InvalidInput(sprintf('There is no "%s" content type; the types are %s.', $type, implode(', ', array_keys($this->types->all()))));

		$slug ??= Slug::from($title);

		if (! Slug::isSlug($slug)) {
			throw new InvalidInput($slug === '' ? 'The title has no characters to make a slug from; pass --slug.' : sprintf('"%s" is not a slug; try "%s".', $slug, Slug::from($slug)));
		}

		$now   = $this->clock->now();
		$dated = $contentType->dateArchives !== DateArchives::None;

		$changes = new EntryChanges(set: [
			'title' => $title,
			...($dated ? ['published' => $now->format('Y-m-d H:i:s P')] : []),
			...($draft ? ['status' => 'draft'] : [])
		], body: "\n");

		try {
			$result = $this->writer->create($contentType, $slug, $changes, $now);
		} catch (WriteException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Created %s', $this->paths->relative("{$this->paths->content}/{$result->path}")));

		return ExitCode::Success;
	}
}
