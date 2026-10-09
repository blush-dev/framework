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
use Blush\Auth\ExtensionAction;
use Blush\Auth\RoleStore;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Application;
use Blush\Extension\ExtensionKind;
use Blush\Media\MediaKind;
use Blush\Tests\BootsScratchSite;

#[CoversClass(Permissions::class)]
#[CoversClass(Capabilities::class)]
#[CoversClass(Capability::class)]
#[CoversClass(ContentAction::class)]
#[CoversClass(ExtensionAction::class)]
#[CoversClass(Role::class)]
#[CoversClass(BuiltInRole::class)]
final class PermissionsTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	protected function setUp(): void
	{
		// Pages don't credit authors unless the site says so (D-329).
		$this->writeTemporaryFile('user/data/types/page.json', '{"kind": "tree"}');
		$this->writeTemporaryFile('user/data/relations/authors.json', '{"kind": "credit", "from": ["page"], "to": ["profile"], "aliases": ["author"]}');
		$this->writeTemporaryFile('user/content/mine.md', "---\nid: 46a0e5fd-ec10-015a-9df1-cf880649d5d2\ntitle: Mine\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/my-draft.md', "---\nid: 7c65ba47-3ddc-81a6-9756-9426c6c0bbf0\ntitle: My draft\nauthors: jane\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/theirs.md', "---\nid: dfa06296-3e2e-c055-9225-577df64507e2\ntitle: Theirs\nauthors: [sam, lee]\n---\n");
		$this->writeTemporaryFile('user/content/their-draft.md', "---\nid: 132aafcd-1c14-f988-a19f-1d615683b2ed\ntitle: Their draft\nauthors: sam\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/my-scheduled.md', "---\nid: c5fede04-af7c-179c-ec65-0471134715f6\ntitle: My scheduled\nauthors: [sam, jane]\npublished: 2099-01-01\n---\n");
		$this->writeTemporaryFile('user/content/nobodys.md', "---\nid: 2a6fa078-34d3-123d-8700-a30179fd7867\ntitle: Nobody's\n---\n");
		$this->writeTemporaryFile('user/content/profiles/jane.md', "---\nid: 04e1cf46-8734-1fc4-7399-c1e7571e878e\ntitle: Jane\n---\n");
		$this->writeTemporaryFile('user/content/profiles/sam.md', "---\nid: dc24b740-5b4f-dcea-63e7-28c113a2936d\ntitle: Sam\n---\n");

		$this->app = $this->scratchApplication(['APP_ENV' => 'development']);
		$this->app->boot();
	}

	private function permissions(): Permissions
	{
		return $this->app->container()->make(Permissions::class);
	}

	private function entry(string $key, string $type = 'page'): Entry
	{
		$entry = $this->app->container()->make(Entries::class)->named($type, $key);
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
		$this->assertTrue($permissions->can($this->account('owner'), 'anything.an.extension.adds'));
		$this->assertFalse($permissions->can($this->account('administrator'), 'anything.an.extension.adds'), 'The administrator has a list (D-500).');
		$this->assertTrue($permissions->can($this->account('administrator'), 'content.page.edit.others'));
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

	public function testMediaIsByKindAndWhoseItIs(): void
	{
		$permissions = $this->permissions();
		$contributor = new Account('jane', 'hash', ['contributor']);
		$editor      = new Account('ed', 'hash', ['editor']);

		$this->assertTrue($permissions->mayUpload($contributor, MediaKind::Image));
		$this->assertFalse($permissions->mayUpload($contributor, MediaKind::Video), 'Images only (D-407).');
		$this->assertTrue($permissions->mayUpload($editor, MediaKind::Document), 'media.*.upload is every kind.');
		$this->assertTrue($permissions->mayChangeMedia($contributor, Capability::MediaEdit, 'jane'), 'Their own.');
		$this->assertFalse($permissions->mayChangeMedia($contributor, Capability::MediaEdit, 'sam'));
		$this->assertFalse($permissions->mayChangeMedia($contributor, Capability::MediaEdit, ''), 'No owner is anyone\'s.');
		$this->assertFalse($permissions->mayChangeMedia($contributor, Capability::MediaDelete, 'jane'));
		$this->assertTrue($permissions->mayChangeMedia($editor, Capability::MediaDelete, ''));
		$this->assertTrue($permissions->usesMedia($contributor));
		$this->assertFalse($permissions->usesMedia(new Account('mo', 'hash', ['member'])));
	}

	public function testDropsRetiredCapabilitiesFromSavedRoles(): void
	{
		$this->writeTemporaryFile('storage/roles.json', (string) json_encode(['roles' => [['name' => 'uploader', 'label' => 'Uploader', 'capabilities' => ['media.upload', 'media.delete']]]]));

		$this->assertSame(['media.delete'], $this->app->container()->make(RoleStore::class)->all()[0]->capabilities ?? null, 'media.upload is gone, with nothing in its place.');
	}

	public function testListsAnAccountsCapabilities(): void
	{
		$capabilities = $this->app->container()->make(Capabilities::class);
		$capabilities->register('shop.orders', 'Manage orders');

		$this->assertContains('shop.orders', $this->permissions()->capabilities($this->account('owner')));
		$this->assertNotContains('shop.orders', $this->permissions()->capabilities($this->account('administrator')));
		$this->assertSame(
			['media.image.upload', 'media.edit', 'content.*.create', 'content.*.edit', 'content.*.delete', 'content.page.create', 'content.page.edit', 'content.page.delete'],
			array_values(array_filter($this->permissions()->capabilities($this->account('contributor')), static fn (string $name): bool => ! str_starts_with($name, 'content.') || preg_match('/^content\.(\*|page)\./', $name) === 1))
		);
		$this->assertContains('content.profile.edit', $this->permissions()->capabilities($this->account('contributor')));
	}

	public function testExtensionActionsNeedSeeingTheirKind(): void
	{
		$permissions = new Permissions(
			new Roles(new AuthConfig(roles: [
				new Role('blind', 'Blind', ['extensions.plugins.delete']),
				new Role('stylist', 'Stylist', ['extensions.*.view', 'extensions.themes.activate'])
			]), new MemoryRoleStore()),
			$this->app->container()->make(Capabilities::class),
			$this->app->container()->make(ContentTypes::class)
		);

		$this->assertFalse($permissions->can($this->account('blind'), 'extensions.plugins.delete'), 'Deleting needs seeing (D-389).');
		$this->assertTrue($permissions->can($this->account('stylist'), ExtensionAction::Activate->on(ExtensionKind::Theme)));
		$this->assertTrue($permissions->can($this->account('stylist'), ExtensionAction::View->on(ExtensionKind::IconPack)), 'Every kind\'s grants each kind\'s.');
		$this->assertFalse($permissions->can($this->account('stylist'), ExtensionAction::Activate->on(ExtensionKind::Plugin)));
		$this->assertTrue($permissions->can($this->account('owner'), 'extensions.plugins.install'));
		$this->assertTrue($permissions->can($this->account('administrator'), 'extensions.plugins.activate'));
		$this->assertTrue($permissions->can($this->account('administrator'), 'extensions.icon-packs.install'), 'Icon packs have no code.');

		foreach (['plugins', 'themes'] as $kind) {
			foreach (['install', 'update', 'delete'] as $action) {
				$this->assertFalse($permissions->can($this->account('administrator'), "extensions.{$kind}.{$action}"), 'Changing code is the owner\'s unless given (D-500).');
			}
		}

		$this->assertFalse($permissions->can($this->account('editor'), 'extensions.themes.view'), 'Only the owner and the administrator have them built in.');
	}

	public function testNamesExtensionCapabilities(): void
	{
		$capabilities = $this->app->container()->make(Capabilities::class);

		$this->assertSame('extensions.icon-packs.activate', ExtensionAction::Activate->on(ExtensionKind::IconPack));
		$this->assertSame([ExtensionKind::IconPack, ExtensionAction::Activate], ExtensionAction::parse('extensions.icon-packs.activate'));
		$this->assertNull(ExtensionAction::parse('extensions.*.view'));
		$this->assertNull(ExtensionAction::parse('extensions.plugins.edit'));
		$this->assertSame('Turn plugins on and off', $capabilities->all()['extensions.plugins.activate'] ?? null);
		$this->assertSame('Activate themes', $capabilities->all()['extensions.themes.activate'] ?? null);
		$this->assertSame('Icon Packs', $capabilities->group('extensions.icon-packs.install'));
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

		$content  = $this->app->container()->make(Entries::class);
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
						array_map(static fn (Entry $entry): string => $entry->path, $allowed),
						array_map(static fn (Entry $entry): string => $entry->path, $found),
						$describe
					);

					$checked += count($allowed);
				}
			}
		}

		$this->assertGreaterThan(0, $checked);
		$this->assertEqualsCanonicalizing(
			['mine.md', 'my-draft.md', 'my-scheduled.md', 'profiles/jane.md', 'their-draft.md'],
			array_map(static fn (Entry $entry): string => $entry->path, $permissions->restrict($this->account('reviewer'), ContentAction::Edit, $content->query()->any())->get()->all()),
			'A reviewer edits their own entries (and profile) and others\' drafts, but not others\' live entries.'
		);
		$this->assertEqualsCanonicalizing(
			['mine.md', 'my-draft.md', 'my-scheduled.md', 'their-draft.md'],
			array_map(static fn (Entry $entry): string => $entry->path, $permissions->restrict($this->account('pager'), ContentAction::Edit, $content->query()->any())->get()->all()),
			'Each type\'s capabilities are its own: publishing pages doesn\'t publish the account\'s live profile, or others\' pages.'
		);
	}
}
