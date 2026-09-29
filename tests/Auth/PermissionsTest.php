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
use Blush\Auth\AuthConfig;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Capabilities;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentTypes;
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
		$this->writeTemporaryFile('user/content/their-draft.md', "---\ntitle: Their draft\nauthors: sam\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/my-scheduled.md', "---\ntitle: My scheduled\nauthors: [sam, jane]\npublished: 2099-01-01\n---\n");
		$this->writeTemporaryFile('user/content/nobodys.md', "---\ntitle: Nobody's\n---\n");

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

	public function testRestrictsQueriesToWhatCanAllows(): void
	{
		$permissions = new Permissions(
			new Roles(new AuthConfig(roles: [
				new Role('reviewer', 'Reviewer', ['content.edit', 'content.edit.others', 'content.publish', 'content.delete']),
				new Role('proofreader', 'Proofreader', ['content.edit.others', 'content.publish.others'])
			])),
			$this->app->container()->make(Capabilities::class),
			new AuthConfig(),
			$this->app->container()->make(ContentTypes::class)
		);

		$content  = $this->app->container()->make(ContentRepository::class);
		$entries  = $content->query()->any()->get()->all();
		$roles    = ['administrator', 'editor', 'author', 'contributor', 'reviewer', 'proofreader', 'ghost'];
		$checked  = 0;

		foreach ($roles as $role) {
			foreach (['jane', null] as $author) {
				foreach (['content.edit', 'content.publish', 'content.delete'] as $capability) {
					$account  = $this->account($role, $author);
					$allowed  = array_values(array_filter($entries, static fn (Entry $entry): bool => $permissions->can($account, $capability, $entry)));
					$found    = $permissions->restrict($account, $capability, $content->query()->any())->get()->all();
					$describe = sprintf('%s (author %s), %s', $role, $author ?? 'none', $capability);

					$this->assertSame(
						array_map(static fn (Entry $entry): string => $entry->id, $allowed),
						array_map(static fn (Entry $entry): string => $entry->id, $found),
						$describe
					);

					$checked += count($allowed);
				}
			}
		}

		$this->assertGreaterThan(0, $checked);
		$this->assertSame(
			['mine.md', 'my-draft.md', 'my-scheduled.md', 'their-draft.md'],
			array_map(static fn (Entry $entry): string => $entry->id, $permissions->restrict($this->account('reviewer'), 'content.edit', $content->query()->any())->get()->all()),
			'A reviewer edits their own entries and others\' drafts, but not others\' live entries.'
		);
	}
}
