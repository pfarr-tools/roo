<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_songbook_id', 'printed_at', 'entry_count'])]
class PrintCheckpoint extends Model
{
    protected $casts = ['printed_at' => 'datetime'];

    public function songbook(): BelongsTo
    {
        return $this->belongsTo(GroupSongbook::class, 'group_songbook_id');
    }
}
