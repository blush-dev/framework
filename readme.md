# Blush Framework

Blush is a flat-file CMS for PHP 8.5. This repository is the framework: the
core that sites (and the `blush-dev/site` skeleton) are built on.

The `2.x` branch is a ground-up rewrite and is under heavy development. There
are no stability guarantees yet, so don't run it in production.

## Documentation

Start with the [documentation](docs/README.md): installing a site, writing
content, themes, configuration, and going live.

## Requirements

- PHP 8.5 or newer
- Composer

## Development

```sh
composer install
composer check   # lint + static analysis + tests
```

Individual tools:

| Command | What it does |
|---|---|
| `composer lint` | Check coding standards (PHPCS) |
| `composer fix` | Fix coding standards automatically (PHPCBF) |
| `composer analyse` | Static analysis (PHPStan, level max) |
| `composer test` | Run the test suite (PHPUnit) |

## License

Blush is licensed under the [MIT License](LICENSE.md).
