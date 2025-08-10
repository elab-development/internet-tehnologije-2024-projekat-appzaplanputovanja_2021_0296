<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key','value'];
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';

    // cache helper
    public static function getValue(string $key, $default = null) {
        return Cache::remember("setting:$key", 300, function () use ($key, $default) {
            $rec = static::query()->find($key);
            return $rec ? $rec->value : $default;
        });
    }

    public static function setValue(string $key, string $value): self {
        Cache::forget("setting:$key");
        return static::updateOrCreate(['key'=>$key], ['value'=>$value]);
    }
}
