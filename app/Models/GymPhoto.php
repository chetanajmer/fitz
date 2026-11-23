<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GymPhoto extends Model
{
    use BelongsToGym;

    protected $fillable = ['path', 'caption', 'sort_order'];

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
