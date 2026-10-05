<?php

/**
 * Admin HTML tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\EntryController;
use Blush\Admin\HtmlGuard;

#[CoversClass(EntryController::class)]
#[CoversClass(HtmlGuard::class)]
final class AdminHtmlTest extends TestCase
{
	use BootsAdmin;

	private const string POST = '_posts/2022-03-29.flame.md';

	private string $token = '';

	/**
	 * Boots a site with Jane's draft, which already has some HTML.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray(['types' => ['post' => ['path' => '_posts', 'date_archives' => true]]]);\n");
		$this->writeTemporaryFile('user/content/' . self::POST, "---\ntitle: Flame\nauthors: jane\nstatus: draft\n---\n\nSome <iframe src=\"https://example.com\"></iframe> here.\n");

		$this->boot(roles: $roles);
		$this->login();

		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? null;
		$this->assertIsString($token);
		$this->token = $token;
	}

	private function save(string $body): ResponseInterface
	{
		$path     = $this->entryPath(self::POST);
		$revision = self::json($this->send('GET', $path))['revision'] ?? null;

		return $this->send('PATCH', $path, json_encode(['revision' => $revision, 'body' => $body]) ?: '', ['X-CSRF-Token' => $this->token]);
	}

	private function create(string $body): ResponseInterface
	{
		return $this->send('POST', '/entries', json_encode(['type' => 'post', 'title' => 'New ' . md5($body), 'body' => $body]) ?: '', ['X-CSRF-Token' => $this->token]);
	}

	private function refused(ResponseInterface $response): string
	{
		$this->assertSame(403, $response->getStatusCode(), (string) $response->getBody());

		$error = self::json($response)['error'] ?? null;
		$this->assertIsString($error);

		return $error;
	}

	public function testWithoutACapabilityNoHtmlCanBeAdded(): void
	{
		$this->site(['author']);

		$this->assertStringContainsString('Your role can\'t add HTML, so `<b>` can\'t be saved.', $this->refused($this->save("Some <iframe src=\"https://example.com\"></iframe> here, <b>now</b>.\n")));
		$this->assertSame(200, $this->save("Some <iframe src=\"https://example.com\"></iframe> there.\n")->getStatusCode(), 'What was there may stay.');
		$this->assertSame(200, $this->save("Some `<b>` and\n\n    <b>code</b>\n")->getStatusCode(), 'HTML in code isn\'t HTML; and what was there may go.');
		$this->refused($this->create("<span>Hi</span>\n"));
		$this->assertSame(201, $this->create("Plain words.\n")->getStatusCode());
	}

	public function testTheAllowedListIsTheEditors(): void
	{
		$this->site(['editor']);

		$this->assertSame(201, $this->create("Press <kbd>Enter</kbd>, <span class=\"x\" data-n=\"1\" aria-label=\"y\">here</span>.\n")->getStatusCode());
		$this->assertStringContainsString('`<span style>` can\'t be added with your role', $this->refused($this->create("<span style=\"color: red\">red</span>\n")));
		$this->assertStringContainsString('`<custom-thing>`', $this->refused($this->create("<custom-thing></custom-thing>\n")));
		$this->assertStringContainsString('`javascript: link`', $this->refused($this->create("[go](javascript:alert(1))\n")));
		$this->assertStringContainsString('`javascript: in <a href>`', $this->refused($this->create("<a href=\"java&#x09;script&colon;x\">go</a>\n")));
	}

	public function testUnfilteredRefusesOnlyWhatsAlwaysRefused(): void
	{
		$this->site(['administrator']);

		$this->assertSame(201, $this->create("<span style=\"color: red\">red</span> <custom-thing></custom-thing>\n")->getStatusCode());
		$this->assertStringContainsString('`<script>`', $this->refused($this->create("<script>alert(1)</script>\n")));
		$this->assertStringContainsString('`<img onerror>`', $this->refused($this->create("<img src=\"x.png\" onerror=\"alert(1)\">\n")));
		$this->assertStringContainsString('`<svg>`', $this->refused($this->create("<svg></svg>\n")));
		$this->assertStringContainsString('`data: in <img src>`', $this->refused($this->create("<img src=\"data:text/html;base64,PHNjcmlwdD4=\">\n")));
		$this->assertSame(201, $this->create("<img src=\"data:image/png;base64,iVBORw0KGgo=\" alt=\"\">\n")->getStatusCode());
	}
}
