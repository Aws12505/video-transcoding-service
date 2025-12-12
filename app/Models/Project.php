<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'project_key',
        'project_name',
        'api_key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function transcodeJobs(): HasMany
    {
        return $this->hasMany(TranscodeJob::class, 'project_key', 'project_key');
    }
}
