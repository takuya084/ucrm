<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRecordProgram extends Model
{
    use HasFactory;

    protected $table = 'support_record_programs';

    protected $fillable = [
        'support_record_id',
        'program_id',
        'duration_minutes',
        'memo',
    ];

    public function supportRecord(): BelongsTo
    {
        return $this->belongsTo(SupportRecord::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
