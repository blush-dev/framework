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
use Blush\Auth\ContentAction;
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
#[CoversClass(ContentAction::class)]
#[CoversClass(Role::class)]
#[CoversClass(BuiltInRole::class)]
final class PermissionsTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	protected function setUp(): void
	{
		// Pages don't credit authors unless the site says so (D-329).
		$this->writeTemporaryFile('user/data/types/page.yaml', "kind: tree\nauthors: true\n");
		$this->writeTemporaryFile('user/content/mine.md', "---\ntitle: Mine\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/my-draft.md', "---\ntitle: My draft\nauthors: jane\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/theirs.md', "---\ntitle: Theirs\nauthors: [sam, lee]\n---\n");
		$this->writeTemporaryFile('user/content/their-draft.md', "---\ntitle: Their draft\nauthors: sam\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/my-scheduled.md', "---\ntitle: My scheduled\nauthors: [sam, jane]\npublished: 2099-01-01\n---\n");
		$this->writeTemporaryFile('user/content/nobodys.md', "---\ntitle: Nobody's\n---\n");
		$this->writeTemporaryFile('user/content/profiles/jane.md', "---\ntitle: Jane\n---\n");
		$this->writeTemporaryFile('user/content/profiles/sam.md', "---\ntitle: Sam\n---\n");

		$this->app = $this->scratchApplication(['APP_ENV' => 'development']);
		$this->app->boot();
	}

	private function permissions(): Permissions
	{
		return $this->app->container()->make(Permissions::class);
	}

	private function entry(string $key, string $type = 'page'): Entry
	{
		$entry = $this->app->container()->make(ContentRepository::class)->named($type, $key);
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
		$this->assertFalse($permissions->can($this->account('ghost'), ContentAction::Edit), 'An unknown role grants nothing.');
		$this->assertTrue($permissions->can(new Account('two', 'hash', ['ghost', 'author']), ContentAction::Edit));
		$this->assertTrue($permissions->can($this->account('author'), 'content.page.edit'), 'Every type\'s capability grants each type\'s.');
		$this->assertTrue($permissions->can($this->account('author'), ContentAction::Create, 'profile'));
	}

	public function testOthersEntriesNeedTheOthersCapability(): void
	{
		$permissions = $this->permissions();

		$this->assertTrue($permissions->can($this->account('author'), ContentAction::Edit, $this->entry('mine')));
		$this->assertFalse($permissions->can($this->account('author'), ContentAction::Edit, $this->entry('theirs')));
		$this->assertTrue($permissions->can($this->account('editor'), ContentAction::Edit, $this->entry('theirs')));
		$this->assertFalse($permissions->can($this->account('author', null), ContentAction::Edit, $this->entry('mine')), 'No author, no entries of its own.');
		$this->assertTrue($permissions->owns($this->account('author'), $this->entry('mine')));
		$this->assertTrue($permissions->can($this->account('author'), ContentAction::Edit, $this->entry('jane', 'profile')), 'An account\'s profile is its own.');
		$this->assertFalse($permissions->can($this->account('author'), ContentAction::Edit, $this->entry('sam', 'profile')));
	}

	public function testContributorsStayInDrafts(): void
	{
		$permissions = $this->permissions();
		$contributor = $this->account('contributor');

		$this->assertTrue($permissions->can($contributor, ContentAction::Edit, $this->entry('my-draft')));
		$this->assertTrue($permissions->can($contributor, ContentAction::Delete, $this->entry('my-draft')));
		$this->assertFalse($permissions->can($contributor, ContentAction::Edit, $this->entry('mine')));
		$this->assertFalse($permissions->can($contributor, ContentAction::Publish, $this->entry('my-draft')));
		$this->assertTrue($permissions->can($this->account('author'), ContentAction::Edit, $this->entry('mine')));
	}

	public function testListsAnAccountsCapabilities(): void
	{
		$capabilities = $this->app->container()->make(Capabilities::class);
		$capabilities->register('shop.orders', 'Manage orders');

		$this->assertContains('shop.orders', $this->permissions()->capabilities($this->account('administrator')));
		$this->assertSame(
			['content.*.create', 'content.*.edit', 'content.*.delete', 'content.page.create', 'content.page.edit', 'content.page.delete'],
			array_values(array_filter($this->permissions()->capabilities($this->account('contributor')), static fn (string $name): bool => ! str_starts_with($name, 'content.') || preg_match('/^content\.(\*|page)\./', $name) === 1))
		);
		$this->assertContains('content.profile.edit', $this->permissions()->capabilities($this->account('contributor')));
	}

	public function testRestrictsQueriesToWhatCanAllows(): void
	{
		$permissions = new Permissions(
			new Roles(new AuthConfig(roles: [
				new Role('reviewer', 'Reviewer', ['content.*.edit', 'content.*.edit.others', 'content.*.publish', 'content.*.delete']),
				new Role('proofreader', 'Proofreader', ['content.*.edit.others', 'content.*.publish.others']),
				new Role('pager', 'Pager', ['content.page.edit', 'content.page.edit.others', 'content.page.publish', 'content.profile.edit'])
			]), new MemoryRoleStore()),
			$this->app->container()->make(Capabilities::class),
			$this->app->container()->make(ContentTypes::class)
		);

		$content  = $this->app->container()->make(ContentRepository::class);
		$entries  = $content->query()->any()->get()->all();
		$roles    = ['administrator', 'editor', 'author', 'contributor', 'reviewer', 'proofreader', 'pager', 'ghost'];
		$checked  = 0;

		foreach ($roles as $role) {
			foreach (['jane', null] as $author) {
				foreach ([ContentAction::Edit, ContentAction::Publish, ContentAction::Delete] as $capability) {
					$account  = $this->account($role, $author);
					$allowed  = array_values(array_filter($entries, static fn (Entry $entry): bool => $permissions->can($account, $capability, $entry)));
					$found    = $permissions->restrict($account, $capability, $content->query()->any())->get()->all();
					$describe = sprintf('%s (author %s), %s', $role, $author ?? 'none', $capability->value);

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
			['mine.md', 'my-draft.md', 'my-scheduled.md', 'profiles/jane.md', 'their-draft.md'],
			array_map(static fn (Entry $entry): string => $entry->id, $permissions->restrict($this->account('reviewer'), ContentAction::Edit, $content->query()->any())->get()->all()),
			'A reviewer edits their own entries (and profile) and others\' drafts, but not others\' live entries.'
		);
		$this->assertSame(
			['mine.md', 'my-draft.md', 'my-scheduled.md', 'their-draft.md'],
			array_map(static fn (Entry $entry): string => $entry->id, $permissions->restrict($this->account('pager'), ContentAction::Edit, $content->query()->any())->get()->all()),
			'Each type\'s capabilities are its own: publishing pages doesn\'t publish the account\'s live profile, or others\' pages.'
		);
	}
}
