# Secrets

A plugin that talks to another service, with an API key or a token it
gets when someone connects an account, keeps those credentials as
**secrets**. The site's operator can set one in the environment (`.env`
or the server's own), or it's stored encrypted, by the admin or by your
code. Either way your code reads it the same way.

## Declaring a secret

Declare each secret in your plugin's
[provider's](../plugins/service-providers.md) `register()`:

```php
use Blush\Secret\SecretDefinition;
use Blush\Secret\Secrets;

$this->container->resolving(Secrets::class, static function (object $secrets): void {
	if ($secrets instanceof Secrets) {
		$secrets->define(new SecretDefinition(
			owner: 'acme/analytics',          // your plugin's name
			key: 'ga_refresh_token',          // lowercase letters, digits, and _
			label: 'Google refresh token',
			description: 'Lets the plugin read reports from Google Analytics.',
			required: true
		));
	}
});
```

A secret's full name is your plugin's name, a dot, and its key:
`acme/analytics.ga_refresh_token`. The environment variable that sets it
is made from that name, `ACME_ANALYTICS_GA_REFRESH_TOKEN`, unless you
name another with `env:`. A value set in the environment always wins,
and storing over it throws `SecretLocked`.

## Reading and storing

Read it through your plugin's scope, asking for `Blush\Secret\Secrets`
in a constructor:

```php
$secrets = $this->secrets->for('acme/analytics');

$token = $secrets->require('ga_refresh_token');   // throws SecretMissing when it isn't set
$client->withToken($token->reveal());

$secrets->get('ga_refresh_token');                 // or null when it isn't set
$secrets->has('ga_refresh_token');                 // whether it's set
$secrets->set('ga_refresh_token', $newToken);      // store it, encrypted
$secrets->remove('ga_refresh_token');
```

A service that replaces its token each time it's used needs the new one
saved before anything else reads the old one. `update()` does that,
holding the store while it runs:

```php
$secrets->update('ga_refresh_token', function (?Secret $now) use ($client): string {
	return $client->refresh($now?->reveal())->refreshToken;
});
```

## Good to know

- **A secret never shows its value** unless you call `reveal()`, so keep
  the `Secret` and reveal it where you use it. Printing one gives
  `[secret]`, dumps and error pages show no value, and it can't be
  serialized, so it can't end up in a cache or session by accident.
  Don't read secrets in `config/` files: config is compiled to a file
  in `storage/cache` as plain text.
- **Every secret is declared.** Reading one that isn't throws
  `InvalidSecret`, and so does storing one on a site without a
  `SECRETS_KEY`. A stored value that won't decrypt (stored under a key
  the site no longer has) throws `SecretUnreadable`; it's never treated
  as missing. All of them extend `Blush\Secret\SecretException`.
- **A scope reaches only its owner's secrets.** That guards against
  mistakes, not a hostile plugin: plugins run in the same PHP process
  and can read the environment themselves. Installing a plugin means
  trusting it.

## Checking and storage

`bin/blush doctor` and Site Health report a required secret that isn't
set, one that won't decrypt, and stored ones nothing declares any more.
On a flat-file site, stored secrets are in `storage/secrets.json`,
encrypted with the site's `SECRETS_KEY`; see
[the commands](../reference/cli/README.md).
