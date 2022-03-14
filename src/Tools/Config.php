<?php
/**
 * Config class.
 *
 * This is a wrapper around the `Collection` class with extra functions for
 * parsing config files.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Tools;

use Blush\Tools\Collection;
use Symfony\Component\Yaml\Yaml;

class Config extends Collection
{
	/**
	 * Parses a YAML file and appends the values to the collection.
	 *
	 * @since 1.0.0
	 */
        public function parseYamlFile( string $filename ) : void
	{
                $config = Yaml::parseFile( $filename );

                if ( ! is_array( $config ) ) {
                        return;
                }

                foreach ( $config as $name => $value ) {
                        $this->add( $name, $value );
                }
        }

	/**
	 * Parses a JSON file and appends the values to the collection.
	 *
	 * @since 1.0.0
	 */
        public function parseJsonFile( string $filename ) : void
	{
                $config = json_decode( file_get_contents( $filename ), true );

                if ( ! is_array( $config ) ) {
                        return;
                }

                foreach ( $config as $name => $value ) {
                        $this->add( $name, $value );
                }
        }
}
