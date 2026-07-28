<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappContact extends Model
{
    use BelongsToTenant;

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'account_id',
        'contact_group_id',
        'phone',
        'name',
        'params',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsappContactGroup::class, 'contact_group_id');
    }
}
