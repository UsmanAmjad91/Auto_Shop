@extends('admin.settings._layout')
@section('settings')
<form method="POST" action="{{ route('settings.save', 'integrations') }}" class="card mb-3">@csrf
    <div class="card-header">VIN decoding & payment gateway</div>
    <div class="card-body">
        <p class="small text-secondary">NHTSA vPIC is free and needs no key. Payment gateway keys are stored encrypted and never sent to the browser.
            Card payments are recorded manually; full card numbers are never stored.</p>
        @include('admin.settings._fields', ['schema' => $schema])
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">Save</button></div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">Configured API integrations
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#integrationModal-new"><i class="bi bi-plus"></i> Add</button></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Name</th><th>Type</th><th>Provider</th><th>Credentials</th><th>Status</th><th>Last test</th><th></th></tr></thead>
        <tbody>
        @forelse($integrations as $i)
            <tr><td class="fw-semibold">{{ $i->name }} @if($i->is_default)<span class="badge text-bg-primary">Default</span>@endif</td>
                <td>{{ status_label($i->type) }}</td><td>{{ $i->provider }}</td>
                <td class="small font-monospace">@foreach($i->maskedCredentials() as $k => $v)<div>{{ $k }}: {{ $v }}</div>@endforeach</td>
                <td><x-status :label="$i->is_active ? 'Active' : 'Inactive'" :color="$i->is_active ? 'success' : 'secondary'" /></td>
                <td class="small">{{ $i->last_tested_at ? $i->last_tested_at->diffForHumans().' ('.$i->last_test_status.')' : '—' }}</td>
                <td class="text-end text-nowrap">
                    <form method="POST" action="{{ route('settings.integrations.test', $i) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">Test</button></form>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#integrationModal-{{ $i->id }}">Edit</button>
                    <form method="POST" action="{{ route('settings.integrations.destroy', $i) }}" class="d-inline" onsubmit="return confirm('Remove integration?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td></tr>
        @empty <tr><td colspan="7" class="text-secondary small">No custom integrations. Add an alternative VIN decoder, payment gateway or other API here.</td></tr> @endforelse
        </tbody></table></div>
</div>

@push('modals')
@foreach($integrations->push(new \App\Models\ApiIntegration(['type' => 'vin_decoder', 'is_active' => true])) as $i)
<x-modal :id="'integrationModal-'.($i->id ?? 'new')" :title="$i->exists ? 'Edit integration' : 'Add integration'" size="lg">
    <form method="POST" action="{{ $i->exists ? route('settings.integrations.update', $i) : route('settings.integrations.store') }}">
        @csrf @if($i->exists) @method('PUT') @endif
        <div class="row g-3">
            <x-form.select name="type" label="Type" :options="['vin_decoder' => 'VIN decoder', 'payment_gateway' => 'Payment gateway', 'email' => 'Email provider', 'sms' => 'SMS provider', 'other' => 'Other API']" :value="$i->type" col="col-md-4" />
            <x-form.input name="name" label="Display name" :value="$i->name" required col="col-md-4" />
            <x-form.input name="provider" label="Provider key" :value="$i->provider" required col="col-md-4" help="e.g. vinaudit, stripe" />
            <x-form.input name="api_key" type="password" label="API key" col="col-md-6" :placeholder="$i->credential('api_key') ? 'saved — leave blank to keep' : ''" autocomplete="new-password" />
            <x-form.input name="api_secret" type="password" label="API secret" col="col-md-6" :placeholder="$i->credential('api_secret') ? 'saved — leave blank to keep' : ''" autocomplete="new-password" />
            <x-form.input name="url" type="url" label="Endpoint URL template" :value="$i->config['url'] ?? null" col="col-12" help="For VIN decoders use {vin} and optionally {key}, e.g. https://api.example.com/v1/decode/{vin}?key={key}" />
            <x-form.input name="auth_header" label="Auth header name (optional)" :value="$i->config['auth_header'] ?? null" col="col-md-6" help="Sends the API key in this header, e.g. X-Api-Key" />
            <x-form.textarea name="field_map" label="Response field map (JSON)" :value="isset($i->config['field_map']) ? json_encode($i->config['field_map']) : '{&quot;make&quot;:&quot;make&quot;,&quot;model&quot;:&quot;model&quot;,&quot;year&quot;:&quot;year&quot;,&quot;trim&quot;:&quot;trim&quot;}'" rows="2" col="col-md-6" help="Our field => dot path in the provider JSON" />
            <x-form.switch name="is_active" label="Active" :checked="$i->is_active" col="col-md-6" />
            <x-form.switch name="is_default" label="Default for its type" :checked="$i->is_default" col="col-md-6" />
        </div>
        <div class="text-end mt-3"><button class="btn btn-primary">Save integration</button></div>
    </form>
</x-modal>
@endforeach
@endpush
@endsection
