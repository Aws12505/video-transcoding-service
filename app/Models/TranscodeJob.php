<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscodeJob extends Model
{
    protected $fillable = [
        'project_key',
        'video_id',
        'qualities_requested',
        'status',
        'callback_url',
        'output_paths',
        'download_count',
        'completed_at',
    ];

    protected $casts = [
        'qualities_requested' => 'array',
        'output_paths' => 'array',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_key', 'project_key');
    }

    public function getUniqueIdentifier(): string
    {
        return "{$this->project_key}_{$this->video_id}";
    }
}
