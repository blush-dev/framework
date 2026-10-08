<?php

/**
 * Array record store conformance test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage\Conformance;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\ArrayRecordStore;
use Blush\Storage\Record\RecordStore;

#[CoversClass(ArrayRecordStore::class)]
#[CoversClass(ArrayEvaluator::class)]
final class ArrayRecordStoreTest extends RecordStoreConformance
{
	#[Override]
	protected function store(): RecordStore
	{
		return new ArrayRecordStore();
	}
}
