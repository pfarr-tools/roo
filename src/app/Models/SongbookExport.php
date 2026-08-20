<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_songbook_id', 'format', 'through_date', 'storage_path', 'entry_count'])]
class SongbookExport extends Model
{
    protected $table = 'group_songbook_exports';
    protected $casts = ['through_date' => 'date'];
    public function songbook(): BelongsTo { return $this->belongsTo(GroupSongbook::class, 'group_songbook_id'); }
}
