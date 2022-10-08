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
use Blush\Contracts\Content\{Entry, Locator, Query};
use Blush\Contracts\Content\Type as TypeContract;
use Blush\Contracts\Content\Types as TypesContract;

// Classes.
use Blush\Core\ServiceProvider;
use Blush\Content\Locator\File as FileLocator;
use Blush\Content\Query\File as FileQuery;
use Blush\Content\Entry\MarkdownFile;
use Blush\Content\Types\{Component, Registry, Type};

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
		$this->app->bind( Entry::class, MarkdownFile::class );

		// Bind content type.
		$this->app->bind( TypeContract::class, Type::class );

		// Bind content type registry singleton.
                $this->app->singleton( TypesContract::class, Registry::class );

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
                        return new Component(
                                $app->make( TypesContract::class ),
                                $types
                        );
                } );

		// Bind the content locator.
		$this->app->bind( Locator::class, FileLocator::class );

		// Bind the content query.
		$this->app->bind( Query::class, function( $app ) {
			return new FileQuery( $app->make( Locator::class ) );
		} );

		// Add aliases.
		$this->app->alias( Entry::class,         'content.entry'   );
		$this->app->alias( Locator::class,       'content.locator' );
		$this->app->alias( Query::class,         'content.query'   );
		$this->app->alias( TypeContract::class,  'content.type'    );
		$this->app->alias( TypesContract::class, 'content.types'   );
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
