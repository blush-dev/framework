<?php

namespace Blush\Cache;

use Blush\Tools\Collection as Collect;

class Collection extends Cache {

	protected function filename() {
		return $this->path( "{$this->name}.json" );
	}

	public function set( $data ) {
		$this->make();

		$this->data = new Collect( $data );

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
			json_encode( $this->data->all(), JSON_PRETTY_PRINT )
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
					$this->data = new Collect( $decoded );
					return $this->data;
				}
			}
		}

		$this->data = new Collect();

		return $this->data;
	}
}
