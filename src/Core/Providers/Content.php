<?php
/**
 * Content service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

// Interfaces.
use Blush\Contracts\Content\{
	ContentEntry,
	ContentLocator,
	ContentQuery,
	ContentType,
	ContentTypes
};

// Classes.
use Blush\Core\ServiceProvider;
use Blush\Content\Locator\File as FileLocator;
use Blush\Content\Query\File as FileQuery;
use Blush\Content\Entry\MarkdownFile;
use Blush\Content\Type\{Component, Type, Types};

class Content extends ServiceProvider
{
	/**
	 * Register bindings.
	 *
	 * @since 1.0.0
	 */
        public function register(): void
	{
		// Bind content entry.
		$this->app->bind( ContentEntry::class, MarkdownFile::class );

		// Bind content type.
		$this->app->bind( ContentType::class, Type::class );

		// Bind content type registry singleton.
                $this->app->singleton( ContentTypes::class, Types::class );

		// Bind content types component and pass types and config in.
                $this->app->singleton( Component::class, function( $app ) {

			// Merge default and user-configured content types.
			$types = array_merge( $app->make( 'config' )->get( 'content' ), [
				'page' => [
					'path'    => '',
					'routing' => false,
					'collect' => false
				]
			] );

			// Creates the content types component.
                        return new Component( $app->make( ContentTypes::class ), $types );
                } );

		// Bind the content locator.
		$this->app->bind( ContentLocator::class, FileLocator::class );

		// Bind the content query.
		$this->app->bind( ContentQuery::class, function( $app ) {
			return new FileQuery( $app->make( ContentLocator::class ) );
		} );

		// Add aliases.
		$this->app->alias( ContentEntry::class,   'content.entry'   );
		$this->app->alias( ContentLocator::class, 'content.locator' );
		$this->app->alias( ContentQuery::class,   'content.query'   );
		$this->app->alias( ContentType::class,    'content.type'    );
		$this->app->alias( ContentTypes::class,   'content.types'   );
        }

	/**
	 * Bootstrap bindings.
	 *
	 * @since 1.0.0
	 */
        public function boot(): void
	{
                $this->app->make( Component::class )->boot();
        }
}
