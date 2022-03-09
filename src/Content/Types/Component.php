<?php
/**
 * Content types component.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Types;

use Blush\Contracts\Bootable;
use Blush\Tools\Collection;

class Component implements Bootable {

	/**
	 * Collection of content types.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Types
	 */
        protected $types;

	/**
	 * Config collection for content types.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Collection
	 */
        protected $config;

	/**
	 * Sets up object state.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  Types      $types
	 * @param  Collection $config
	 * @return void
	 */
        public function __construct( Types $types, Collection $config ) {
                $this->types  = $types;
                $this->config = $config;
        }

	/**
	 * Registers content types on boot.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function boot() {
                foreach ( $this->config as $type => $options ) {
                        $this->types->add( $type, $options );
                }
        }
}
