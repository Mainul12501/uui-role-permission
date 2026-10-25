# Contributing

Use PHP 8.1-compatible syntax and preserve support for Laravel 10 through 13. Install dependencies with `composer install`, then run:

```sh
composer validate --strict
composer lint
composer format:test
composer analyse
composer test
```

Changes to migrations, authorization, cache invalidation, or guardrails must include regression tests. Never rewrite an adoption migration in a way that removes host application data. Security reports belong in the private process described in `SECURITY.md`, not a public issue.
