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

use Blush\Activity\Actor;
use Blush\Admin\AdminLinks;
use Blush\Auth\Accounts;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\EntryEditor;
use Blush\Content\Editing\NewEntry;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Source\ContentFiles;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\TypeKind;
use Blush\Support\Slug;

/**
 * Creates an entry through the one write path (`EntryEditor`, D-756), so
 * the activity log lists it (D-758): as the account `--account` names,
 * with its permissions and its byline, else as the command line, which
 * may do anything.
 * The file is named by the type's pattern from the slug (from the title
 * unless `--slug` is given; D-511), and every entry gets a `published`
 * date (D-514); `--draft` marks the entry as a draft, and `--parent`
 * puts a tree's page under another (D-408). Existing files are never
 * overwritten. On a terminal it asks for what isn't passed (D-721): the
 * type, the title, the slug (made from the title), the parent for a
 * tree, and whether it's published or a draft. It ends with where the
 * admin edits it.
 */
#[Command('content:new', 'Create a new entry.')]
final readonly class CreateContent
{
	public function __construct(
		private ContentTypes $types,
		private ContentFiles $contentFiles,
		private Entries $content,
		private AdminLinks $links,
		private EntryEditor $editor,
		private Accounts $accounts
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The content type; asked for when it isn\'t given.')] ?string $type = null,
		#[Argument('The entry title; asked for when it isn\'t given.')] ?string $title = null,
		#[Option('The slug; defaults to one made from the title.')] ?string $slug = null,
		#[Option('Create the entry as a draft.')] bool $draft = false,
		#[Option('The page to put it under, by its path (a tree\'s pages only).')] ?string $parent = null,
		#[Option('The account it\'s made as, by username, with its permissions and byline; the command line when left out.')] ?string $account = null
	): ExitCode {
		$actor = $this->actor($account);

		$type      ??= $prompt->select('Type', $this->typeOptions());
		$contentType = $this->types->find($type)
			?? throw new InvalidInput(sprintf('There is no "%s" content type; the types are %s.', $type, implode(', ', array_keys($this->types->all()))));

		$title ??= $prompt->ask('Title', null, placeholder: sprintf('The %s\'s title', $contentType->labels->singular));
		$made    = Slug::from($title);
		$slug  ??= $prompt->ask('Slug', $made === '' ? null : $made, self::slugProblem(...));

		$problem = self::slugProblem($slug);

		if ($problem !== null) {
			throw new InvalidInput($slug === '' ? 'The title has no characters to make a slug from; pass --slug.' : $problem);
		}

		$under = $this->parent($prompt, $contentType, $parent);
		$draft = $draft || $prompt->select('Status', ['published' => 'Published', 'draft' => 'Draft'], 'published') === 'draft';

		try {
			$entry = $this->editor->create($actor, new NewEntry(
				type: $contentType->name,
				title: $title,
				slug: $slug,
				status: $draft ? 'draft' : 'published',
				parent: $under?->key
			));
		} catch (EditRefused $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success($this->contentFiles->kept()
			? sprintf('Created %s', $this->contentFiles->source()->location($entry->path))
			: sprintf('Created the %s "%s" (%s).', $contentType->labels->item, $entry->title, $entry->key));

		$edit = $this->links->entry($entry);

		if ($edit !== null) {
			$output->next("Edit it in the admin: `{$edit}`");
		}

		return ExitCode::Success;
	}

	/**
	 * Returns who the entry is made as: an account that may still act, by
	 * username, or the command line.
	 *
	 * @throws InvalidInput When there's no such account, or it's suspended.
	 */
	private function actor(?string $username): Actor
	{
		if ($username === null) {
			return Actor::commandLine();
		}

		$account = $this->accounts->find($username) ?? throw new InvalidInput(sprintf('There\'s no "%s" account.', $username));

		return $account->suspended
			? throw new InvalidInput(sprintf('%s is suspended, so it can\'t make entries.', $account->username))
			: Actor::cli($account);
	}

	/**
	 * Returns the types to pick from, by their singular labels.
	 *
	 * @return array<string, string>
	 */
	private function typeOptions(): array
	{
		$options = [];

		foreach ($this->types->all() as $name => $type) {
			$options[(string) $name] = "{$type->labels->singular} ({$name})";
		}

		asort($options, SORT_NATURAL | SORT_FLAG_CASE);

		return $options;
	}

	/**
	 * Returns the page a tree's new page goes under: the one passed, by
	 * path, else asked for from the tree's pages by title (none first).
	 * Other kinds have no parent.
	 *
	 * @throws InvalidInput When the type isn't a tree, or the page isn't there.
	 */
	private function parent(Prompt $prompt, ContentType $type, ?string $parent): ?Entry
	{
		if ($type->kind() !== TypeKind::Tree) {
			return $parent === null ? null : throw new InvalidInput(sprintf('Only a tree\'s pages have parents, and %s isn\'t one.', $type->name));
		}

		if ($parent === null) {
			$options = ['' => 'None: at the top'];

			foreach ($this->content->query()->type($type->name)->anyStatus()->limit(null)->orderBy('title')->get()->all() as $page) {
				$options[$page->key] = "{$page->title} ({$page->key})";
			}

			$parent = count($options) === 1 ? '' : $prompt->select('Parent page', $options, '');
		}

		if ($parent === '') {
			return null;
		}

		return $this->content->named($type->name, trim($parent, '/'))
			?? throw new InvalidInput(sprintf('There\'s no %s at "%s" to put it under.', $type->labels->item, $parent));
	}

	/**
	 * Returns why text can't be a slug, or `null` when it can.
	 */
	private static function slugProblem(string $slug): ?string
	{
		return Slug::isSlug($slug) ? null : sprintf('"%s" is not a slug; try "%s".', $slug, Slug::from($slug));
	}
}
