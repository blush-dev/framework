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
use Blush\Contracts\Template\View as ViewContract;
use Blush\Core\ServiceProvider;

// Concretes.
use Blush\Template\{Engine, View};
use Blush\Template\Tags\PoweredBy;

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

		// Add powered-by singleton.
		$this->app->singleton( PoweredBy::class );

		// Add aliases.
		$this->app->alias( ViewContract::class,   'template.view'   );
		$this->app->alias( EngineContract::class, 'template.engine' );
		$this->app->alias( PoweredBy::class,      'poweredby'       );
	}
}
