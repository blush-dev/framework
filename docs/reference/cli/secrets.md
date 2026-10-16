# Secrets

[Secrets](../../features/secrets.md) are credentials Blush and plugins use to
reach other services. None of these commands shows a value.

| Command | What it does |
|---|---|
| `secrets:list` | List the secrets core and plugins declare: where each is set (`environment`, `stored`, `missing`, or `unreadable`), the environment variable that sets it, and when it was stored. Stored secrets nothing declares any more are listed too. |
| `secrets:set <name>` | Store a secret's value, encrypted with `SECRETS_KEY`, asked for without showing it: `secrets:set acme/analytics.ga_refresh_token`. A secret set in the environment can't be stored over. |
| `secrets:remove <name>` | Remove a secret's stored value, declared or left behind by a removed plugin. One set in the environment stays set. |
| `secrets:rotate` | Encrypt every stored secret again under the current `SECRETS_KEY`. To change the key: put the old one in `SECRETS_KEY_PREVIOUS`, a new one in `SECRETS_KEY` (`php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`), run this, then remove `SECRETS_KEY_PREVIOUS`. |
