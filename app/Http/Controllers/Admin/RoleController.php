<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles.index', ['roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'protected' => config('autoshop.protected_roles')]);
    }

    public function create()
    {
        return view('admin.roles.form', ['role' => new Role, 'matrix' => $this->matrix(), 'granted' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        AuditLogger::log('role_created', $role, "Role {$role->name} created", [], ['permissions' => $data['permissions'] ?? []]);

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role)
    {
        return view('admin.roles.form', ['role' => $role, 'matrix' => $this->matrix(),
            'granted' => $role->permissions->pluck('name')->all(),
            'locked' => in_array($role->name, config('autoshop.protected_roles'), true)]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if(in_array($role->name, config('autoshop.protected_roles'), true), 403, 'This role is protected.');
        $data = $this->validated($request, $role);
        $before = $role->permissions->pluck('name')->all();
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        AuditLogger::log('role_updated', $role, "Role {$role->name} permissions updated", ['permissions' => $before], ['permissions' => $data['permissions'] ?? []]);

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Request $request, Role $role)
    {
        abort_if(in_array($role->name, config('autoshop.protected_roles'), true), 403, 'This role is protected.');
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Reassign the users of this role before deleting it.']);
        }
        AuditLogger::log('role_deleted', $role, "Role {$role->name} deleted");
        $role->delete();

        return $this->done($request, 'Role deleted.', route('roles.index'));
    }

    protected function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
    }

    /** module => [action => permission name] */
    protected function matrix(): array
    {
        $out = [];
        foreach (Permission::orderBy('name')->pluck('name') as $name) {
            [$module, $action] = explode('.', $name, 2);
            $out[$module][$action] = $name;
        }

        return $out;
    }
}
