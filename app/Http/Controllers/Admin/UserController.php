<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserApprovalRequest;
use App\Notifications\AccountStatusChanged;
use App\Services\AuditLogger;
use App\Support\DataTable;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('admin.users.index', ['roles' => Role::orderBy('name')->pluck('name'),
                'counts' => User::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')]);
        }
        $me = $request->user();

        return DataTable::make(User::query()->with('roles'), $request)
            ->searchable(['name', 'email', 'phone'])
            ->sortable(['name' => 'name', 'email' => 'email', 'status' => 'status', 'created_at' => 'created_at', 'last_login_at' => 'last_login_at'], 'created_at')
            ->filters(function ($q, $r) {
                $q->when($r->status, fn ($x) => $x->where('status', $r->status))
                    ->when($r->role, fn ($x) => $x->role($r->role));
            })
            ->transform(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
                'avatar' => $u->avatarUrl(), 'roles' => $u->roles->pluck('name')->implode(', '),
                'status' => ['label' => status_label($u->status), 'color' => status_color($u->status)], 'email_verified' => (bool) $u->email_verified_at,
                'two_factor' => $u->hasTwoFactorEnabled(),
                'last_login_at' => $u->last_login_at?->diffForHumans(), 'created_at' => fmt_date($u->created_at),
                'url' => route('users.show', $u),
                'actions' => array_values(array_filter([
                    ['label' => 'View', 'icon' => 'eye', 'url' => route('users.show', $u)],
                    $me->can('update', $u) ? ['label' => 'Edit', 'icon' => 'pencil', 'url' => route('users.edit', $u)] : null,
                    $me->can('approve', $u) && $u->status !== 'approved' ? ['label' => 'Approve', 'icon' => 'check-lg', 'url' => route('users.status', $u), 'method' => 'POST', 'data' => ['status' => 'approved']] : null,
                    $me->can('approve', $u) && $u->status === 'pending' ? ['label' => 'Reject', 'icon' => 'x-lg', 'url' => route('users.status', $u), 'method' => 'POST', 'data' => ['status' => 'rejected'], 'confirm' => 'Reject this registration?'] : null,
                    $me->can('approve', $u) && $u->status === 'approved' ? ['label' => 'Suspend', 'icon' => 'slash-circle', 'url' => route('users.status', $u), 'method' => 'POST', 'data' => ['status' => 'suspended'], 'confirm' => 'Suspend this account?'] : null,
                    $me->can('delete', $u) ? ['label' => 'Delete', 'icon' => 'trash', 'url' => route('users.destroy', $u), 'method' => 'DELETE', 'confirm' => 'Delete this user?', 'danger' => true] : null,
                ])),
            ])->response();
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['status' => 'approved']), 'roles' => $this->assignableRoles()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create(collect($data)->only(['name', 'email', 'phone', 'password', 'status'])->all());
            $user->forceFill(['email_verified_at' => $request->boolean('mark_verified') ? now() : null,
                'approved_at' => $data['status'] === 'approved' ? now() : null, 'approved_by' => auth()->id()])->save();
            $user->syncRoles($data['roles'] ?? []);

            return $user;
        });

        return redirect()->route('users.show', $user)->with('success', 'User created.');
    }

    public function show(User $user)
    {
        return view('admin.users.show', [
            'user' => $user->load('roles', 'employee', 'approvalRequests.reviewer'),
            'logins' => $user->loginHistories()->limit(25)->get(),
            'activity' => AuditLog::where('user_id', $user->id)->latest('id')->limit(25)->get(),
        ]);
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('admin.users.form', ['user' => $user, 'roles' => $this->assignableRoles()]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($user, $data, $request) {
            $user->fill(collect($data)->only(['name', 'email', 'phone'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            if ($user->id !== $request->user()->id) {
                $before = $user->getRoleNames()->all();
                $user->syncRoles($data['roles'] ?? []);
                AuditLogger::log('roles_changed', $user, 'Roles changed', ['roles' => $before], ['roles' => $data['roles'] ?? []]);
                if (($data['status'] ?? $user->status) !== $user->status) {
                    $this->applyStatus($user, $data['status']);
                }
            }
        });

        return redirect()->route('users.show', $user)->with('success', 'User updated.');
    }

    public function status(Request $request, User $user)
    {
        $this->authorize('approve', $user);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(config('autoshop.enums.user_statuses')))],
            'notes' => ['nullable', 'string', 'max:1000'], 'roles' => ['array'], 'roles.*' => ['exists:roles,name']]);

        DB::transaction(function () use ($user, $data) {
            if ($data['status'] === 'approved' && ! empty($data['roles'])) {
                $user->syncRoles($this->filterAssignable($data['roles']));
            } elseif ($data['status'] === 'approved' && $user->roles()->count() === 0) {
                $user->assignRole('Employee');
            }
            $this->applyStatus($user, $data['status'], $data['notes'] ?? null);
        });

        return $this->done($request, "Account {$data['status']}.");
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer'], 'action' => ['required', 'in:approved,rejected,suspended,disabled']]);
        $n = 0;
        foreach (User::whereIn('id', $data['ids'])->get() as $user) {
            if ($request->user()->can('approve', $user)) {
                if ($data['action'] === 'approved' && $user->roles()->count() === 0) {
                    $user->assignRole('Employee');
                }
                $this->applyStatus($user, $data['action']);
                $n++;
            }
        }

        return $this->done($request, "$n account(s) updated.");
    }

    protected function applyStatus(User $user, string $status, ?string $notes = null): void
    {
        $old = $user->status;
        if ($old === $status) {
            return;
        }
        $user->forceFill(['status' => $status] + ($status === 'approved' ? ['approved_at' => now(), 'approved_by' => auth()->id()] : []))->save();

        UserApprovalRequest::where('user_id', $user->id)->where('status', 'pending')
            ->update(['status' => $status, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'notes' => $notes]);

        if (in_array($status, ['suspended', 'disabled', 'rejected'], true)) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
        }
        AuditLogger::log('status_changed', $user, "Account {$old} → {$status}", ['status' => $old], ['status' => $status]);

        try {
            $user->notify(new AccountStatusChanged($status, $notes));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        $user->delete();

        return $this->done($request, 'User deleted.', route('users.index'));
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::in(array_keys(config('autoshop.enums.user_statuses')))],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($this->assignableRoles()->all())],
        ]);
    }

    /** Only Super Administrators can grant the Super Administrator role. */
    protected function assignableRoles()
    {
        return Role::orderBy('name')->pluck('name')
            ->reject(fn ($r) => $r === 'Super Administrator' && ! auth()->user()->hasRole('Super Administrator'))->values();
    }

    protected function filterAssignable(array $roles): array
    {
        return array_values(array_intersect($roles, $this->assignableRoles()->all()));
    }

    public function export(Request $request)
    {
        $rows = User::with('roles')->when($request->status, fn ($q) => $q->where('status', $request->status))->orderBy('name')->get()
            ->map(fn ($u) => ['name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'roles' => $u->getRoleNames()->implode(', '),
                'status' => $u->status, 'verified' => (bool) $u->email_verified_at, 'two_factor' => $u->hasTwoFactorEnabled(),
                'last_login' => $u->last_login_at?->toDateTimeString(), 'created' => $u->created_at->toDateString()]);

        return Exporter::download($request->input('format', 'csv'), 'Users', ['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'roles' => 'Roles',
            'status' => 'Status', 'verified' => 'Email verified', 'two_factor' => '2FA', 'last_login' => 'Last login', 'created' => 'Registered'], $rows);
    }
}
