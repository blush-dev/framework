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
use Blush\Contracts\Content\Entry as EntryContract;
use Blush\Contracts\Content\Locator as LocatorContract;
use Blush\Contracts\Content\Query as QueryContract;

// Classes.
use Blush\Core\ServiceProvider;
use Blush\Content\{Locator, Query};
use Blush\Content\Entry\MarkdownFile;
use Blush\Content\Types\{Component, Types};

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
		$this->app->bind( EntryContract::class, MarkdownFile::class );

		// Bind content types singleton.
                $this->app->singleton( Types::class );

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
                                $app->make( Types::class ),
                                $types
                        );
                } );

		// Bind the content locator.
		$this->app->bind( LocatorContract::class, Locator::class );

		// Bind the content query.
		$this->app->bind( QueryContract::class, function( $app ) {
			return new Query(
				$app->make( LocatorContract::class )
			);
		} );

		// Add aliases.
		$this->app->alias( EntryContract::class,   'content.entry'   );
		$this->app->alias( LocatorContract::class, 'content.locator' );
		$this->app->alias( QueryContract::class,   'content.query'   );
		$this->app->alias( Types::class,           'content.types'   );
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
