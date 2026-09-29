<?php

/**
 * Permission tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Account;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Core\Application;
use Blush\Tests\BootsScratchSite;

#[CoversClass(Permissions::class)]
#[CoversClass(Capabilities::class)]
#[CoversClass(Capability::class)]
#[CoversClass(BuiltInRole::class)]
final class PermissionsTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->writeTemporaryFile('user/content/mine.md', "---\ntitle: Mine\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/my-draft.md', "---\ntitle: My draft\nauthors: jane\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/theirs.md', "---\ntitle: Theirs\nauthors: [sam, lee]\n---\n");

		$this->app = $this->scratchApplication(['APP_ENV' => 'development']);
		$this->app->boot();
	}

	private function permissions(): Permissions
	{
		return $this->app->container()->make(Permissions::class);
	}

	private function entry(string $key): Entry
	{
		$entry = $this->app->container()->make(ContentRepository::class)->named('page', $key);
		$this->assertNotNull($entry, $key);

		return $entry;
	}

	private function account(string $role, ?string $author = 'jane'): Account
	{
		return new Account('someone', 'hash', [$role], $author);
	}

	public function testRolesGrantTheirCapabilities(): void
	{
		$permissions = $this->permissions();

		$this->assertTrue($permissions->can($this->account('editor'), Capability::SitePublish));
		$this->assertFalse($permissions->can($this->account('author'), 'site.publish'));
		$this->assertTrue($permissions->can($this->account('administrator'), 'anything.an.extension.adds'));
		$this->assertFalse($permissions->can($this->account('ghost'), 'content.edit'), 'An unknown role grants nothing.');
		$this->assertTrue($permissions->can(new Account('two', 'hash', ['ghost', 'author']), 'content.edit'));
	}

	public function testOthersEntriesNeedTheOthersCapability(): void
	{
		$permissions = $this->permissions();

		$this->assertTrue($permissions->can($this->account('author'), 'content.edit', $this->entry('mine')));
		$this->assertFalse($permissions->can($this->account('author'), 'content.edit', $this->entry('theirs')));
		$this->assertTrue($permissions->can($this->account('editor'), 'content.edit', $this->entry('theirs')));
		$this->assertFalse($permissions->can($this->account('author', null), 'content.edit', $this->entry('mine')), 'No author, no entries of its own.');
		$this->assertTrue($permissions->owns($this->account('author'), $this->entry('mine')));
	}

	public function testContributorsStayInDrafts(): void
	{
		$permissions = $this->permissions();
		$contributor = $this->account('contributor');

		$this->assertTrue($permissions->can($contributor, 'content.edit', $this->entry('my-draft')));
		$this->assertTrue($permissions->can($contributor, 'content.delete', $this->entry('my-draft')));
		$this->assertFalse($permissions->can($contributor, 'content.edit', $this->entry('mine')));
		$this->assertFalse($permissions->can($contributor, 'content.publish', $this->entry('my-draft')));
		$this->assertTrue($permissions->can($this->account('author'), 'content.edit', $this->entry('mine')));
	}

	public function testListsAnAccountsCapabilities(): void
	{
		$capabilities = $this->app->container()->make(Capabilities::class);
		$capabilities->register('shop.orders', 'Manage orders');

		$this->assertContains('shop.orders', $this->permissions()->capabilities($this->account('administrator')));
		$this->assertSame(['content.create', 'content.edit', 'content.delete'], $this->permissions()->capabilities($this->account('contributor')));
	}
}
