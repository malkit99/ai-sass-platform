<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappContactGroup extends Model
{
    use BelongsToTenant;

    public const STATUS_ENABLE = 'enable';

    public const STATUS_DISABLE = 'disable';

    protected $fillable = [
        'account_id',
        'name',
        'status',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(WhatsappContact::class, 'contact_group_id');
    }
}
