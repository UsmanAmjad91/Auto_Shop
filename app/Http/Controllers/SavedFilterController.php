<?php

namespace App\Http\Controllers;

use App\Models\SavedFilter;
use Illuminate\Http\Request;

class SavedFilterController extends Controller
{
    public function index(Request $request, string $module)
    {
        return SavedFilter::where('user_id', $request->user()->id)->where('module', $module)->orderBy('name')->get(['id', 'name', 'filters']);
    }

    public function store(Request $request, string $module)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'filters' => ['required', 'array']]);
        $filters = collect($data['filters'])->filter(fn ($v) => is_scalar($v) && $v !== '')->map(fn ($v) => mb_substr((string) $v, 0, 200))->all();
        $saved = SavedFilter::updateOrCreate(['user_id' => $request->user()->id, 'module' => mb_substr($module, 0, 50), 'name' => $data['name']], ['filters' => $filters]);

        return response()->json($saved->only('id', 'name', 'filters'));
    }

    public function destroy(Request $request, SavedFilter $savedFilter)
    {
        abort_unless($savedFilter->user_id === $request->user()->id, 403);
        $savedFilter->delete();

        return response()->json(['ok' => true]);
    }
}
