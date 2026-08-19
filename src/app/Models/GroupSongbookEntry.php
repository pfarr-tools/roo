<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_songbook_id', 'song_version_id', 'song_number', 'added_at'])]
class GroupSongbookEntry extends Model
{
    protected $casts = ['added_at' => 'datetime'];
    public function songVersion(): BelongsTo { return $this->belongsTo(SongVersion::class); }
    public function songbook(): BelongsTo { return $this->belongsTo(GroupSongbook::class, 'group_songbook_id'); }
}
