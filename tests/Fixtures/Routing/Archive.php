<?php

/**
 * Attribute-routed controller fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Http\Response;
use Blush\Routing\Attributes\Get;
use Blush\Routing\Attributes\Group;
use Blush\Routing\Attributes\Post;
use Blush\Routing\Attributes\Route;
use Blush\Routing\RouteMatch;

#[Group('/archives', name: 'archive.', middleware: [Tag::class])]
final readonly class Archive
{
	#[Get('/{year}', name: 'year')]
	public function year(int $year, ServerRequestInterface $request): ResponseInterface
	{
		$match     = $request->getAttribute(RouteMatch::class);
		$attribute = $request->getAttribute('year');

		return Response::text(sprintf(
			'year:%s:%s:%s',
			var_export($year, true),
			is_string($attribute) ? $attribute : 'none',
			$match instanceof RouteMatch ? $match->route->name : 'none'
		));
	}

	#[Get('/{year}/{month:\d{2}}', name: 'month')]
	public function month(int $year, string $month, int $page = 1): ResponseInterface
	{
		return Response::text("month:{$year}-{$month}:{$page}");
	}

	#[Get('/color/{color}', name: 'color')]
	public function color(Color $color): ResponseInterface
	{
		return Response::text("color:{$color->name}");
	}

	#[Post('/search')]
	#[Route('/search', methods: ['put'])]
	public function search(): ResponseInterface
	{
		return Response::text('search');
	}
}
