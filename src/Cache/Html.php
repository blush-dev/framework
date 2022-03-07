<?php

namespace Blush\Cache;

class Html extends Cache {

	protected function filename() {
		return $this->path( "{$this->name}.html" );
	}

	public function set( $data ) {
		$this->make();
		$this->data = $data;

		file_put_contents( $this->filename(), $this->data );
	}

	public function get() {

		if ( $this->data ) {
			return $this->data;
		}

		if ( file_exists( $this->filename() ) ) {
			$this->data = file_get_contents( $this->filename() );
			return $this->data;
		}

		$this->data = '';

		return $this->data;
	}
}
