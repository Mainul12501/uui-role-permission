<?php

namespace Mainul\UuiRolePermission;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Mainul\UuiRolePermission\Adapters\DatabaseAuditRecorder;
use Mainul\UuiRolePermission\Adapters\NullAuditRecorder;
use Mainul\UuiRolePermission\Adapters\NullSessionRevoker;
use Mainul\UuiRolePermission\Adapters\SanctumSessionRevoker;
use Mainul\UuiRolePermission\Console\AdoptCommand;
use Mainul\UuiRolePermission\Console\AssignCommand;
use Mainul\UuiRolePermission\Console\CacheCommand;
use Mainul\UuiRolePermission\Console\DoctorCommand;
use Mainul\UuiRolePermission\Console\InstallCommand;
use Mainul\UuiRolePermission\Console\PruneCommand;
use Mainul\UuiRolePermission\Console\SuperAdminCommand;
use Mainul\UuiRolePermission\Console\SyncCommand;
use Mainul\UuiRolePermission\Contracts\AuditRecorder;
use Mainul\UuiRolePermission\Contracts\PermissionRegistry;
use Mainul\UuiRolePermission\Contracts\PermissionResolver;
use Mainul\UuiRolePermission\Contracts\SessionRevoker;
use Mainul\UuiRolePermission\Contracts\TenantResolver;
use Mainul\UuiRolePermission\Contracts\UserActivityChecker;
use Mainul\UuiRolePermission\Events\AccessControlChanged;
use Mainul\UuiRolePermission\Events\MembershipChanged;
use Mainul\UuiRolePermission\Events\MembershipRemoved;
use Mainul\UuiRolePermission\Events\RoleAssigned;
use Mainul\UuiRolePermission\Events\RoleCreated;
use Mainul\UuiRolePermission\Events\RoleDeleted;
use Mainul\UuiRolePermission\Events\RolePermissionsSynced;
use Mainul\UuiRolePermission\Events\RoleRevoked;
use Mainul\UuiRolePermission\Events\RoleUpdated;
use Mainul\UuiRolePermission\Http\Middleware\RequireAnyPermission;
use Mainul\UuiRolePermission\Http\Middleware\RequirePermission;
use Mainul\UuiRolePermission\Http\Middleware\RequireRole;
use Mainul\UuiRolePermission\Jobs\PruneAuditLogs;
use Mainul\UuiRolePermission\Listeners\FlushAccessCache;
use Mainul\UuiRolePermission\Listeners\QueuedRecordAudit;
use Mainul\UuiRolePermission\Listeners\RecordAudit;
use Mainul\UuiRolePermission\Listeners\RevokeSessionsOnAccessRemoval;
use Mainul\UuiRolePermission\Listeners\WarmPermissionsOnAccessChange;
use Mainul\UuiRolePermission\Support\AccessCache;
use Mainul\UuiRolePermission\Support\ConfigPermissionRegistry;
use Mainul\UuiRolePermission\Support\ConfiguredUserActivityChecker;
use Mainul\UuiRolePermission\Support\DatabasePermissionResolver;
use Mainul\UuiRolePermission\Support\RouteTenantResolver;

class UuiRolePermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/uui-role-permission.php', 'uui-role-permission');
        $this->app->bind(PermissionRegistry::class, function ($app): PermissionRegistry {
            $registry = config('uui-role-permission.registry_class');

            return $registry ? $app->make($registry) : new ConfigPermissionRegistry;
        });
        $this->app->bind(TenantResolver::class, fn ($app) => $app->make(config('uui-role-permission.tenancy.resolver', RouteTenantResolver::class)));
        $this->app->bind(UserActivityChecker::class, fn ($app) => $app->make(config('uui-role-permission.user_activity_checker', ConfiguredUserActivityChecker::class)));
        $this->app->bind(AuditRecorder::class, function () {
            $driver = config('uui-role-permission.audit.driver', 'database');

            return $driver === 'null' ? new NullAuditRecorder : ($driver === 'database' ? new DatabaseAuditRecorder : app($driver));
        });
        $this->app->bind(SessionRevoker::class, function () {
            $driver = config('uui-role-permission.sessions.revoker', 'auto');
            if ($driver === 'null') {
                return new NullSessionRevoker;
            }
            if ($driver === 'auto') {
                return class_exists(Sanctum::class) ? new SanctumSessionRevoker : new NullSessionRevoker;
            }

            return app($driver);
        });
        $this->app->scoped(AccessCache::class, function ($app): AccessCache {
            $store = config('uui-role-permission.cache.store');
            $cache = $store ? $app['cache']->store($store) : $app['cache']->store();

            return new AccessCache($cache);
        });
        $this->app->scoped(PermissionResolver::class, fn ($app): PermissionResolver => new DatabasePermissionResolver($app->make(AccessCache::class), $app->make(UserActivityChecker::class)));
    }

    public function boot(Router $router): void
    {
        $this->publishes([__DIR__.'/../config/uui-role-permission.php' => config_path('uui-role-permission.php')], 'uui-role-permission-config');
        $this->publishes([__DIR__.'/../database/migrations/' => database_path('migrations')], 'uui-role-permission-migrations');
        $this->publishes([__DIR__.'/../stubs/UuiRolePermissionSeeder.php.stub' => database_path('seeders/UuiRolePermissionSeeder.php')], 'uui-role-permission-seeder');
        $this->publishes([__DIR__.'/../stubs/permissions.php.stub' => config_path('permissions.php')], 'uui-role-permission-registry');
        if (config('uui-role-permission.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
        $accessEvents = [
            AccessControlChanged::class,
            RoleCreated::class,
            RoleUpdated::class,
            RolePermissionsSynced::class,
            RoleDeleted::class,
            RoleAssigned::class,
            RoleRevoked::class,
            MembershipChanged::class,
            MembershipRemoved::class,
        ];
        foreach ($accessEvents as $eventClass) {
            Event::listen($eventClass, [FlushAccessCache::class, 'handle']);
            Event::listen($eventClass, config('uui-role-permission.audit.async', config('uui-role-permission.queue.audit_async', true)) ? QueuedRecordAudit::class : [RecordAudit::class, 'handle']);
            Event::listen($eventClass, [RevokeSessionsOnAccessRemoval::class, 'handle']);
            Event::listen($eventClass, [WarmPermissionsOnAccessChange::class, 'handle']);
        }

        foreach (config('uui-role-permission.middleware_aliases', []) as $type => $alias) {
            $middleware = $type === 'role' ? RequireRole::class : ($type === 'any_permission' ? RequireAnyPermission::class : RequirePermission::class);
            $router->aliasMiddleware($alias, $middleware);
        }

        if (config('uui-role-permission.register_gate', true)) {
            Gate::before(function ($user, string $ability, array $arguments = []) {
                if (! method_exists($user, 'hasPermission')) {
                    return null;
                }
                $superAdmin = method_exists($user, 'isSuperAdmin') && call_user_func([$user, 'isSuperAdmin']);
                if (! $superAdmin && ! array_key_exists($ability, app(PermissionRegistry::class)->all())) {
                    return null;
                }
                $tenant = $arguments[0] ?? null;

                return call_user_func([$user, 'hasPermission'], $ability, $tenant) ? true : null;
            });
        }

        Blade::if('permission', function (string $permission, $tenant = null): bool {
            $user = auth()->user();

            return $user !== null && method_exists($user, 'hasPermission') && call_user_func([$user, 'hasPermission'], $permission, $tenant);
        });
        Blade::if('role', function (string $role, $tenant = null): bool {
            $user = auth()->user();

            return $user !== null && method_exists($user, 'hasRole') && call_user_func([$user, 'hasRole'], $role, $tenant);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SyncCommand::class, PruneCommand::class, InstallCommand::class, AdoptCommand::class, DoctorCommand::class, AssignCommand::class, SuperAdminCommand::class, CacheCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if ($cron = config('uui-role-permission.schedule.prune_expired_assignments')) {
                $schedule->command('uui-rp:prune')->cron($cron)->withoutOverlapping()->onOneServer();
            }
            if ($cron = config('uui-role-permission.schedule.prune_audit_logs')) {
                $schedule->job(new PruneAuditLogs)->cron($cron)->withoutOverlapping()->onOneServer();
            }
            if ($cron = config('uui-role-permission.schedule.warm_cache')) {
                $schedule->command('uui-rp:cache warm')->cron($cron)->withoutOverlapping()->onOneServer();
            }
        });
        if (config('uui-role-permission.api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
    }
}
