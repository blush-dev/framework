<?php

namespace Blush\Tools;

use Blush\Tools\Collection;
use Symfony\Component\Yaml\Yaml;

class Config extends Collection {

        public function parseYamlFile( $filename ) {
                $config = Yaml::parseFile( $filename );

                if ( ! is_array( $config ) ) {
                        return;
                }

                foreach ( $config as $name => $value ) {
                        $this->add( $name, $value );
                }
        }

        public function parseJsonFile( $filename ) {
                $config = json_decode( file_get_contents( $filename ), true );

                if ( ! is_array( $config ) ) {
                        return;
                }

                foreach ( $config as $name => $value ) {
                        $this->add( $name, $value );
                }
        }
}
