<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildProgramProgress extends Model
{
    protected $table = 'child_program_progress';

    protected $fillable = ['child_id', 'program_item_id', 'status', 'achieved_at'];

    protected $casts = ['achieved_at' => 'date'];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function programItem(): BelongsTo
    {
        return $this->belongsTo(ProgramItem::class);
    }
}
