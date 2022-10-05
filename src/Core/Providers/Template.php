<?php
/**
 * Template service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

// Abstracts.
use Blush\Contracts\Template\Engine as EngineContract;
use Blush\Contracts\Template\Tags;
use Blush\Contracts\Template\View as ViewContract;
use Blush\Core\ServiceProvider;

// Concretes.
use Blush\Template\{Component, Engine, View};
use Blush\Template\Tags\{PoweredBy, Registry};
use Blush\Tools\Collection;

class Template extends ServiceProvider
{
	/**
	 * Register bindings.
	 *
	 * @since 1.0.0
	 */
        public function register(): void
	{
		// Add template engine.
		$this->app->singleton( EngineContract::class, Engine::class );
                $this->app->bind(      ViewContract::class,   View::class   );

		// Bind tag registry.
		$this->app->singleton( Tags::class, Registry::class );

		// Binds the template component.
		$this->app->singleton( Component::class, function( $app ) {
			return new Component(
				$app->make( Tags::class ),
				$app->make( 'config' )->get( 'template.tags' )
			);
		} );

		// Add powered-by singleton.
		$this->app->singleton( PoweredBy::class );

		// Add aliases.
		$this->app->alias( Tags::class,           'template.tags'   );
		$this->app->alias( ViewContract::class,   'template.view'   );
		$this->app->alias( EngineContract::class, 'template.engine' );
		$this->app->alias( PoweredBy::class,      'poweredby'       );
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
