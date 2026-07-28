<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'account_id',
        'pipeline_id',
        'stage_id',
        'name',
        'phone',
        'email',
        'description',
        'source',
        'ai_score',
        'is_hot',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_hot' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'lead_labels');
    }
}
