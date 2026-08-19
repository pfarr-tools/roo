<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['teaching_group_id', 'title_page_path', 'title_page_original_name', 'title_page_mime_type', 'title_page_size'])]
class GroupSongbook extends Model
{
    public function group(): BelongsTo { return $this->belongsTo(TeachingGroup::class, 'teaching_group_id'); }
    public function entries(): HasMany { return $this->hasMany(GroupSongbookEntry::class)->orderBy('song_number'); }
}
