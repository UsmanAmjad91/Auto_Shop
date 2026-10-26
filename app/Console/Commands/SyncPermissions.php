<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Creates any permissions/roles defined in config/autoshop.php. Existing role customisations are kept unless --reset. */
class SyncPermissions extends Command
{
    protected $signature = 'autoshop:sync-permissions {--reset : Reset default roles to their configured permissions}';

    protected $description = 'Create module permissions and default roles';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $all = [];
        foreach (config('autoshop.permissions') as $module => $actions) {
            foreach ($actions as $action) {
                $all[] = Permission::firstOrCreate(['name' => "$module.$action", 'guard_name' => 'web'])->name;
            }
        }

        foreach (config('autoshop.roles') as $roleName => $grants) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if ($role->wasRecentlyCreated || $this->option('reset') || $roleName === 'Super Administrator') {
                $perms = collect($all)->filter(function ($p) use ($grants) {
                    foreach ($grants as $g) {
                        if ($g === '*' || $g === $p || (str_ends_with($g, '.*') && str_starts_with($p, substr($g, 0, -1)))) {
                            return true;
                        }
                    }

                    return false;
                })->values()->all();
                $role->syncPermissions($perms);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info(count($all).' permissions and '.count(config('autoshop.roles')).' default roles synchronised.');

        return self::SUCCESS;
    }
}
