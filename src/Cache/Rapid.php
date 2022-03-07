<?php

namespace Blush\Cache;

class Rapid extends Cache {

	protected function filename() {
		return '';
	}

	public function make() {}

	public function set( $data ) {
		$this->data = $data;
	}
}
