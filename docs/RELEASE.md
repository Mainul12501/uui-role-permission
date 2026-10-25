# Release checklist

The GitHub repository must be named `Mainul12501/uui-role-permission`; the Composer package name is already `mainul/uui-role-permission`.

## First GitHub release

Run these commands from the package root, not the host Laravel repository:

```sh
git init -b main
git add .
git commit -m "Initial mainul/uui-role-permission release"
git remote add origin https://github.com/Mainul12501/uui-role-permission.git
git push -u origin main
git tag -a v0.1.0 -m "v0.1.0"
git push origin v0.1.0
```

Before tagging, confirm GitHub Actions passes the test, fresh-application smoke, and static workflows. Laravel 10 is end-of-life and its compatibility lane intentionally allows Composer to resolve packages with published advisories; the current Laravel lane still runs `composer audit`.

## Packagist

1. Sign in to Packagist with GitHub.
2. Submit `https://github.com/Mainul12501/uui-role-permission`.
3. Enable the GitHub hook so future tags update automatically.
4. Confirm Packagist displays package name `mainul/uui-role-permission` and version `v0.1.0`.

Install it in a host application with:

```sh
composer require mainul/uui-role-permission:^0.1
```

Do not add a Composer path repository to production applications. Composer installs the tagged package under `vendor/mainul/uui-role-permission`.

## Host adoption

After installation, publish configuration, map the existing tables and models, and run adoption in dry-run mode before applying anything:

```sh
php artisan vendor:publish --tag=uui-role-permission-config
php artisan uui-rp:adopt --dry-run
php artisan uui-rp:adopt --apply
php artisan uui-rp:sync
php artisan uui-rp:doctor
```

Then add `HasRoles` to the user model, replace the application's permission middleware alias with the package alias, and route all role/membership changes through package actions. Keep API exposure disabled until the host permission registry contains the configured API management permissions.
