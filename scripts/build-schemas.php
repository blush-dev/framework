<?php

/**
 * Writes the editor JSON Schemas to `resources/schemas` (D-206). Run it
 * with `composer schemas` after changing a manifest or a field type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

use Blush\Core\Framework;
use Blush\JsonSchema\JsonSchemas;

require dirname(__DIR__) . '/vendor/autoload.php';

foreach (new JsonSchemas()->all() as $file => $schema) {
	file_put_contents(Framework::path(JsonSchemas::DIRECTORY . "/{$file}"), JsonSchemas::encode($schema));

	echo JsonSchemas::DIRECTORY . "/{$file}\n";
}
