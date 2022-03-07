<?php

namespace Blush\Contracts\Markdown;

interface Parser {

        public function convert( string $content );

        public function content();

        public function frontMatter();
}
