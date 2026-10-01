<?php

/**
 * Admin content types controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Blush\Content\Parser\DocumentFormat;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Http\AuthorsController;
use Blush\Content\Type\Authors;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataTypeWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\TypeOrigin;
use Blush\Core\Paths;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Taxonomy;
use Blush\Field\Field;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `GET {path}/api/types` (D-233, D-234): the site's content types, so
 * the admin can list each type's entries and offer to create one. A type
 * is described by its name, its `labels` for people (D-278),
 * its `description` (`''` for none) and `icon` (`null` for its kind's),
 * its kind (`collection`, `taxonomy`, `pages`, or `authors`), whether it's
 * dated (its new entries get a publish date and a dated file name), and
 * whether its entries credit `authors` (D-329). A taxonomy adds the
 * `types` its terms group (empty for every type), which places it in the
 * admin's navigation, and whether it's `hierarchical`; the authors type
 * adds the `types` that credit authors. Each also has its `origin` (`built-in`,
 * `extension`, `config`, or `data`), its `folder`, its URL `prefix` (or
 * `null` without URLs), and how many `fields` it defines (D-250).
 * Taxonomies and the authors type come last. Beside them, `authors`
 * names the authors type, which accounts' authors belong to and the
 * admin lists with people rather than content, or `null` when the site
 * has none.
 *
 * `GET {path}/api/types/{name}` (`show()`) adds the type's own fields
 * (`Field::toArray()`), the `taxonomies` that group it, whether it's
 * `public`, has a `feed`, is in the `sitemap`, and is `editable` (only
 * `user/data/types` types are, D-311), its `dateArchives`, the prefix its
 * folder gives (`folderPrefix`), the data `file` it's defined in (`null`
 * for the rest), its `index` page (`{"id", "title"}`, or `null`), the
 * word its author archives sit under (`authorsWord`: the word, `false`
 * for none, or `null` for a type without URLs, D-329), and its
 * `authorsPage` (`{"id", "title"}`, or `null`).
 * The list adds whether types can be created here (`create`: data types
 * are read) and whether they may set URLs (`urls`).
 *
 * `detail()` describes a type among any set of types, so a change can be
 * answered with the types it made (`TypeEditController`).
 */
final readonly class TypesController
{
	public function __construct(
		private ContentTypes $types,
		private ContentConfig $config,
		private DataTypeWriter $writer,
		private Paths $paths
	) {}

	public function __invoke(): ResponseInterface
	{
		$types = array_values($this->types->all());

		usort($types, static fn (ContentType $a, ContentType $b): int => [$a->hasTerms(), $a->labels->plural] <=> [$b->hasTerms(), $b->labels->plural]);

		$types = array_map(fn (ContentType $type): array => $this->summary($this->types, $type), $types);

		return Response::json([
			'types'   => $types,
			'authors' => $this->types->authors()?->name,
			'create'  => $this->config->dataTypes,
			'urls'    => $this->config->dataTypeUrls
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(string $name): ResponseInterface
	{
		$type = $this->types->find($name);

		if ($type === null) {
			return Response::json(['error' => sprintf('There\'s no "%s" content type.', $name)], Status::NotFound, ['Cache-Control' => 'no-store']);
		}

		return Response::json($this->detail($this->types, $type), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a type among a set of types, as `show()` does.
	 *
	 * @return array<string, mixed>
	 */
	public function detail(ContentTypes $types, ContentType $type): array
	{
		$name       = $type->name;
		$taxonomies = array_values(array_map(
			static fn (Taxonomy $taxonomy): string => $taxonomy->name,
			array_filter($types->taxonomies(), static fn (Taxonomy $taxonomy): bool => $taxonomy->name !== $name && ($taxonomy->types === [] || in_array($name, $taxonomy->types, true)))
		));

		$editable = $types->origin($name)->isEditable() && $this->config->dataTypes;

		try {
			$file = $types->origin($name) === TypeOrigin::Data ? $this->writer->path($name) : null;
		} catch (InvalidContentType) {
			$file = null;
		}

		return [
			...$this->summary($types, $type),
			'public'       => $type->public,
			'feed'         => $type->hasFeed(),
			'sitemap'      => $type->sitemap,
			'editable'     => $editable && $file !== null,
			'taxonomies'   => $taxonomies,
			'fields'       => array_values(array_map(static fn (Field $field): array => array_diff_key($field->toArray(), ['class' => true]), $type->schema->fields)),
			'dateArchives' => $type->dateArchives->value,
			'folderPrefix' => DataTypeWriter::folderPrefix($type->folder),
			'file'         => $file === null ? null : $this->paths->relative($file),
			'index'        => $this->index($type),
			'authorsWord'  => $type->urls === false ? null : $type->urls->authors,
			'authorsPage'  => $this->page($type, AuthorsController::PAGE, $types->authors()->labels->plural ?? 'Authors')
		];
	}

	/**
	 * A collection's or taxonomy's index page (D-255), found on disk so a
	 * type just created has one: the `index` file in its folder.
	 *
	 * @return ?array{id: string, title: string}
	 */
	private function index(ContentType $type): ?array
	{
		return $this->page($type, 'index', $type->labels->plural);
	}

	/**
	 * A file in a type's folder, found on disk: `{"id", "title"}`, titled
	 * with its own `title` or the fallback, or `null`.
	 *
	 * @return ?array{id: string, title: string}
	 */
	private function page(ContentType $type, string $name, string $fallback): ?array
	{
		if ($type->folder === '') {
			return null;
		}

		foreach (DocumentFormat::cases() as $format) {
			$id = "{$type->folder}/{$name}.{$format->value}";

			if (is_file("{$this->paths->content}/{$id}")) {
				return ['id' => $id, 'title' => self::title("{$this->paths->content}/{$id}") ?? $fallback];
			}
		}

		return null;
	}

	/**
	 * The `title` in a file's front matter, if it's on a line of its own.
	 */
	private static function title(string $path): ?string
	{
		$head = (string) @file_get_contents($path, length: 4096);

		return preg_match('/^title:\s*["\']?(.+?)["\']?\s*$/m', $head, $match) === 1 ? $match[1] : null;
	}

	/**
	 * A type as the list describes it.
	 *
	 * @return array<string, mixed>
	 */
	private function summary(ContentTypes $types, ContentType $type): array
	{
		return [
			'name'        => $type->name,
			'labels'      => $type->labels->all(),
			'description' => $type->description,
			'icon'        => $type->icon,
			'kind'        => $type->kind()->value,
			'dated'       => $type->dateArchives !== DateArchives::None,
			'authors'     => $type->authors,
			...($type instanceof Taxonomy ? ['types' => $type->types, 'hierarchical' => $type->hierarchical] : []),
			...($type instanceof Authors ? ['types' => array_keys(array_filter($types->all(), static fn (ContentType $credits): bool => $credits->authors))] : []),
			'origin'      => $types->origin($type->name)->value,
			'folder'      => $type->folder,
			'prefix'      => $type->hasUrls() ? '/' . $type->prefix() : null,
			'fields'      => count($type->schema->fields)
		];
	}
}
