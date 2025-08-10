<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    // dozvoljene postavke (ključ => tip)
    private array $allowed = [
        'outbound_start'           => 'time', // HH:MM
        'checkin_time'             => 'time',
        'checkout_time'            => 'time',
        'return_start'             => 'time',
        'buffer_after_outbound_min'=> 'int',  // minute
        'buffer_before_return_min' => 'int',
        'default_day_start'        => 'time',
        'default_day_end'          => 'time',
    ];

    public function index() {
        return response()->json(Setting::all());
    }

    public function show(string $key) {
        $setting = Setting::find($key);
        if (!$setting) return response()->json(['message'=>'Not found'], 404);
        return response()->json($setting);
    }

    // upsert jednog ključa
    public function upsert(Request $request) {
        $key = $request->string('key')->trim();
        if (!$key || !isset($this->allowed[$key])) {
            return response()->json(['message'=>'Key not allowed'], 422);
        }

        // validacija vrednosti po tipu
        $rules = ['key' => ['required', Rule::in(array_keys($this->allowed))]];
        if ($this->allowed[$key] === 'time') {
            $rules['value'] = ['required','date_format:H:i'];
        } else { // int
            $rules['value'] = ['required','integer','between:0,1440'];
        }

        $data = $request->validate($rules);
        $rec  = Setting::setValue($data['key'], (string)$data['value']);

        return response()->json($rec, 201);
    }

    // batch upsert više ključeva odjednom
    public function batch(Request $request) {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['message'=>'items[] required'], 422);
        }

        $out = [];
        foreach ($items as $i => $row) {
            $key = $row['key'] ?? null;
            $value = $row['value'] ?? null;
            if (!$key || !isset($this->allowed[$key])) {
                return response()->json(['message'=>"Key not allowed at index $i"], 422);
            }
            if ($this->allowed[$key] === 'time') {
                $request->validate(["items.$i.value" => 'required|date_format:H:i']);
            } else {
                $request->validate(["items.$i.value" => 'required|integer|between:0,1440']);
            }
            $out[] = Setting::setValue($key, (string)$value);
        }

        return response()->json($out, 201);
    }
}
