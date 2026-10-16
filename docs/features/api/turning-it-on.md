# Turning on the API

The [API](README.md) is off until you turn it on, in the admin on
**Settings → API**, or in `config/api.php`:

```php
<?php

declare(strict_types=1);

use Blush\Api\ApiConfig;

return new ApiConfig(enabled: true);
```

It's served at `/api/v1`. To serve it somewhere else, set `path`
(`path: '/data'` serves `/data/v1`). While it's off, every address
under it is your site's ordinary "not found" page. See
[Configuration](../../reference/configuration/README.md) for every option.

## A private API

To keep the API to your own front end, apps, and agents, set **Who can
read it** to **Only with a Token** on **Settings → API**, or
`access` in `config/api.php`:

```php
return new ApiConfig(enabled: true, access: ApiAccess::Token);
```

Then a request without a [token](tokens.md) gets a `401`.
