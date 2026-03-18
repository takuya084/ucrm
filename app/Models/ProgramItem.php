<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramItem extends Model
{
    protected $fillable = ['program_id', 'name', 'difficulty_order'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
