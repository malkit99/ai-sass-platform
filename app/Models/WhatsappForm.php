<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappForm extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const SUCCESS_ACTION_MESSAGE = 'message';

    public const SUCCESS_ACTION_REDIRECT = 'redirect';

    public const FIELD_TYPES = [
        'heading', 'paragraph', 'text', 'email', 'whatsapp', 'number',
        'textarea', 'dropdown', 'radio', 'checkboxes', 'date', 'time', 'file',
    ];

    // Types that don't collect a value — display-only.
    public const DISPLAY_ONLY_TYPES = ['heading', 'paragraph'];

    protected $fillable = [
        'account_id',
        'channel_id',
        'name',
        'slug',
        'status',
        'fields',
        'automation_config',
        'success_message',
        'success_action',
        'success_redirect_url',
        'submissions_count',
        'revenue',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'automation_config' => 'array',
            'revenue' => 'decimal:2',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WhatsappFormSubmission::class, 'form_id');
    }

    public function fieldsOfType(string $type): array
    {
        return array_values(array_filter($this->fields ?? [], fn ($f) => ($f['type'] ?? null) === $type));
    }
}
