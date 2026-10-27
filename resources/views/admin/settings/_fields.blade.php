{{-- Renders a settings schema: key => [label, type, rules, options?] --}}
<div class="row g-3">
@foreach($schema as $key => $def)
    @php
        [$label, $type] = $def;
        $field = str_replace('.', '__', $key);
        $value = $settings->get($key);
        $wide = in_array($type, ['textarea', 'switch'], true);
    @endphp
    <div class="{{ $wide ? 'col-12' : 'col-md-6' }}">
        @switch($type)
            @case('switch')
                <x-form.switch :name="$field" :label="$label" :checked="filter_var($value, FILTER_VALIDATE_BOOLEAN)" />
                @break
            @case('select')
                <x-form.select :name="$field" :label="$label" :options="$def[3]" :value="$value" :searchable="count($def[3]) > 20" />
                @break
            @case('textarea')
                <x-form.textarea :name="$field" :label="$label" :value="$value" rows="3" />
                @break
            @case('secret')
                <x-form.input :name="$field" type="password" :label="$label" autocomplete="new-password"
                    :placeholder="$settings->hasSecret($key) ? '•••••••• (saved — leave blank to keep)' : 'Not set'"
                    help="Stored encrypted. Type __clear__ to remove." />
                @break
            @case('image')
                <label class="form-label">{{ $label }}</label>
                <div class="d-flex gap-2 align-items-center">
                    @if($value)<img src="{{ asset('storage/'.$value) }}" alt="" style="max-height:40px" class="border rounded p-1 bg-white">@endif
                    <input type="file" name="{{ $field }}" class="form-control" accept="image/*">
                </div>
                @if($value)<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="{{ $field }}_remove" value="1" id="{{ $field }}_remove"><label class="form-check-label small" for="{{ $field }}_remove">Remove</label></div>@endif
                @break
            @default
                <x-form.input :name="$field" :type="$type" :label="$label" :value="$value" :step="$type === 'number' ? 'any' : null" />
        @endswitch
    </div>
@endforeach
</div>
