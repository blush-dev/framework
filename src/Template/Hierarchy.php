<?php
/**
 * Static utility class for building a top-level template hierarchy.
 *
 * IMPORTANT! This class and its method should not be considered finalized. This
 * is an experimental method for cleaning up some of the code in our controllers.
 * I'm not 100% happy with the code and want to explore various methods for
 * creating as small and consistent of a footprint as possible.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template;

use Blush\Contracts\Content\{Entry, Type};
use Blush\Template\Feed\Feed;

class Hierarchy
{
	/**
	 * Returns the default single template hierarchy.
	 *
	 * @since 1.0.0
	 */
	public static function single( Entry $entry ): array
	{
		$entry_name = $entry->name();
		$type_name  = $entry->type()->name();
		$model_name = static::modelName( $entry->type() );

		return array_merge( $entry->viewPaths(), [
			"single-{$type_name}-{$entry_name}",
			"single-{$type_name}",
			"single-{$model_name}",
			'single',
			'index'
		] );
	}

	/**
	 * Returns the error 404 single template hierarchy.
	 *
	 * @todo  Create an `error` content type.
	 * @since 1.0.0
	 */
	public static function error404(): array
	{
		return [
			'single-error-404',
			'single-error',
			'single',
			'index'
		];
	}

	/**
	 * Returns the homepage single template hierarchy.
	 *
	 * @since 1.0.0
	 */
	public static function singleHome( Entry $entry ): array
	{
		$entry_name = $entry->name();
		$type_name  = $entry->type()->name();
		$model_name = static::modelName( $entry->type() );

		return array_merge( $entry->viewPaths(), [
			'single-home',
			"single-{$type_name}-{$entry_name}",
			"single-{$type_name}",
			"single-{$model_name}",
			'single',
			'index'
		] );
	}

	/**
	 * Returns the default collection template hierarchy.
	 *
	 * @since 1.0.0
	 */
	public static function collection( Entry $entry ): array
	{
		$type_name  = $entry->type()->name();
		$model_name = static::modelName( $entry->type() );

		return [
			"collection-{$type_name}",
			"collection-{$model_name}",
			'collection',
			'index'
		];
	}

	/**
	 * Returns the homepage collection template hierarchy.
	 *
	 * @since 1.0.0
	 */
	public static function collectionHome( Entry $entry ): array
	{
		return array_merge( [
			'collection-home'
		], static::collection( $entry ) );
	}

	/**
	 * Returns the term collection template hierarchy.
	 *
	 * @since 1.0.0
	 */
	public static function collectionTerm( Entry $entry ): array
	{
		$entry_name = $entry->name();
		$type_name  = $entry->type()->name();

		return [
			"collection-{$type_name}-{$entry_name}",
			"collection-{$type_name}",
			'collection-term',
			'collection',
			'index'
		];
	}

	/**
	 * Returns the date collection template hierarchy.
	 *
	 * @since 1.0.0
	 */
	public static function collectionDate( Type $type ): array
	{
		$type_name = $type->name();

		return [
			"collection-datetime-{$type_name}",
			"collection-{$type_name}",
			'collection-datetime',
			'collection',
			'index'
		];
	}

	/**
	 * Helper method for getting a content type's model name. This is a
	 * precursor to a larger content-type blueprint object planned for the
	 * future.
	 *
	 * @since 1.0.0
	 */
	protected static function modelName( Type $type ): string
	{
		return $type->isTaxonomy() ? 'taxonomy' : 'content';
	}
}
