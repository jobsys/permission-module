<?php

namespace Modules\Permission\Console;

use App\Boot\BootPermission;
use Illuminate\Console\Command;
use Modules\Permission\Entities\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'permission:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync permission from config.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $existing_permissions = Permission::all();
        $remaining_permissions = collect();


        $predefined_permissions = BootPermission::permissions();

        foreach ($predefined_permissions as $permission => $menu) {
            if (is_array($menu)) {
                if (!isset($menu['display_name'])) {
                    $this->error("Permission group {$permission} must have a display name.");
                    return;
                }
                $this->info("Syncing permission: $permission => {$menu['display_name']}...");
                $this->syncPermission($existing_permissions, $permission, $menu['display_name'], $remaining_permissions);

                if (isset($menu['children']) && is_array($menu['children'])) {
                    foreach ($menu['children'] as $api_permission => $action) {
                        $this->info("Syncing permission: $api_permission => $action...");
                        $this->syncPermission($existing_permissions, $api_permission, $action, $remaining_permissions);
                    }
                }
            } else {
                $this->info("Syncing permission: $permission => $menu...");
                $this->syncPermission($existing_permissions, $permission, $menu, $remaining_permissions);
            }
        }


        $remove_permissions = $existing_permissions->pluck('name')->diff($remaining_permissions);

        if ($remove_permissions->count() > 0) {
            $this->info("Removing permissions: " . $remove_permissions->implode(', '));
            Permission::whereIn('name', $remove_permissions)->delete();
        }

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->output->success('Sync permission successfully.');
    }

    private function syncPermission($existing_permissions, $permission, $display_name, &$remaining_permissions)
    {
        if ($existing_permissions->where('name', $permission)->count() == 0) {
            Permission::create([
                'name' => $permission,
                'display_name' => $display_name,
                'description' => $display_name,
                'is_active' => 1
            ]);
        } else {
            Permission::where("name", $permission)->update([
                'display_name' => $display_name,
                'description' => $display_name,
                'is_active' => 1
            ]);
            $remaining_permissions->push($permission);
        }
    }
}
