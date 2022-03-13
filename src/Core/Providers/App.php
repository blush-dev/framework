<?php
/**
 * App service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

use Blush\Core\ServiceProvider;
use Blush\Template\Engine;
use Blush\Template\View;
use Blush\Tools\Collection;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\{HtmlDumper, CliDumper};

class App extends ServiceProvider {

	/**
	 * Register bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function register() {

                // Get the app config collection.
                $app_config = $this->app->resolve( 'config.app' );

                // Sets the default timezone.
                date_default_timezone_set( $app_config['timezone'] ?? 'America/Chicago' );

		// Add cache.
                $this->app->instance( 'cache', new Collection() );

		// Add template engine.
                $this->app->bind( View::class );
		$this->app->singleton( Engine::class );


		VarDumper::setHandler( function( $var ) {
			$cloner = new VarCloner();
			$html_dumper = new HtmlDumper();
			$html_dumper->setTheme( 'light' );
			$html_dumper->setStyles( [
				'default' =>
					'--dump-text-mono: \"Source Code Pro\", Monaco, Consolas, \"Andale Mono WT\", \"Andale Mono\", \"Lucida Console\", \"Lucida Sans Typewriter\", \"DejaVu Sans Mono\", \"Bitstream Vera Sans Mono\", \"Liberation Mono\", \"Nimbus Mono L\", \"Courier New\", Courier, monospace;
					background: #f8fafc;
					margin: 2rem;
					max-width: 100%;
					padding: 2rem;
					border: 1px solid #e2e8f0;
					border-bottom-color: #cbd5e1;
					color: #334155;
					font-size: 18px;
					font-family: var( --dump-text-mono );
					line-height:1.75;
					overflow: auto !important;
					word-wrap: normal;
					white-space: revert;
					position: relative;
					z-index: 99999;
					word-break: break-all;
					border-radius: 0;
					box-shadow: none;
					box-sizing: border-box;',
				'index' => 'color: #60a5fa;',
				'note' => 'color: #1d4ed8;',
				'ref' => 'color: #3b82f6;',
				'meta' => 'color: #9333ea;',
				'num' => 'color: #60a5fa;',
				'private' => 'color: #64748b;',
				'protected' => 'color: #475569;',
		                'key' => 'color: #16a34a;',
		                'str' => 'color: #16a34a;',
				'toggle' => 'padding: 0 0.5rem'
			] );


			$dumper = PHP_SAPI === 'cli' ? new CliDumper() : $html_dumper;

			$dumper->dump( $cloner->cloneVar( $var ) );
		});
        }
}
