<?php

namespace Blush\Cache;

use Blush\Tools\Collection;

class Json extends Cache {

	protected function filename() {
		return $this->path( "{$this->name}.json" );
	}

	public function set( $data ) {
		$this->make();

		$this->data = (array) $data;

		$json = preg_replace(
			[
				"/\n\s\s\s\s\s\s\s\s\s\s\s\s\s\s\s\s/",
				"/\n\s\s\s\s\s\s\s\s\s\s\s\s/",
				"/\n\s\s\s\s\s\s\s\s/",
				"/\n\s\s\s\s/"
			],
			[
				"\n\t\t\t\t",
				"\n\t\t\t",
				"\n\t\t",
				"\n\t"
			],
			json_encode( $this->data, JSON_PRETTY_PRINT )
		);

		file_put_contents( $this->filename(), $json );
	}

	public function get() {

		if ( $this->data ) {
			return $this->data;
		}

		if ( file_exists( $this->filename() ) ) {

			$contents = file_get_contents( $this->filename() );

			if ( $contents ) {

				$decoded = json_decode( $contents, true );

				if ( $decoded ) {
					$this->data = $decoded;
					return $this->data;
				}
			}
		}

		$this->data = [];

		return $this->data;
	}
}
