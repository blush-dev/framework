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
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Http\Response;

/**
 * Answers `GET {path}/api/types` (D-233, D-234): the site's content types, so
 * the admin can list each type's entries and offer to create one. A type
 * is described by its name, its `label` and `singular` names for people,
 * its kind (`collection`, `taxonomy`, or `pages`), and whether it's dated
 * (its new entries get a publish date and a dated file name). Taxonomies
 * come last.
 */
final readonly class TypesController
{
	public function __construct(
		private ContentTypes $types
	) {}

	public function __invoke(): ResponseInterface
	{
		$types = array_values(array_map(static fn (ContentType $type): array => [
			'name'     => $type->name,
			'label'    => $type->label,
			'singular' => $type->singular,
			'kind'     => $type->kind()->value,
			'dated'    => $type->dateArchives !== DateArchives::None
		], $this->types->all()));

		usort($types, static fn (array $a, array $b): int => [$a['kind'] === 'taxonomy', $a['label']] <=> [$b['kind'] === 'taxonomy', $b['label']]);

		return Response::json(['types' => $types], headers: ['Cache-Control' => 'no-store']);
	}
}
