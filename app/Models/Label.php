<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'account_id',
        'name',
        'color',
    ];

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_labels');
    }
}
