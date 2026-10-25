# Upgrade guide

## Adopting an existing application schema

1. Back up the database and publish `uui-role-permission-config`.
2. Set `models.user`, tenancy model/key, and all table names to the existing schema. Set `run_migrations` to `false` until the schema has been reviewed.
3. Run `php artisan uui-rp:adopt --dry-run` and compare the reported tables and columns with the application's schema. Existing rows are not rewritten by adoption inspection.
4. After reviewing the diff, run `php artisan uui-rp:adopt --apply` to apply the package's additive migrations. The create migration skips existing tables; review any app-owned schema differences and use a guarded migration for missing host-specific indexes.
5. Move permission definitions into the config registry, run `uui-rp:sync`, then migrate application authorization call sites to `HasRoles` and the package middleware.

For 0.x upgrades, inspect this changelog and back up before schema changes. No automatic destructive migration is provided.
