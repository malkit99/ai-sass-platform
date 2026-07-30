<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappShortLink extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'account_id',
        'channel_id',
        'reference_name',
        'phone',
        'message',
        'slug',
        'clicks',
    ];

    protected $appends = [
        'wa_me_url',
        'short_url',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * The actual WhatsApp click-to-chat URL a visitor ends up at — used by
     * ShortLinkRedirectController and shown as reference alongside short_url,
     * but not the link that's meant to be shared, since it can't track clicks.
     */
    public function getWaMeUrlAttribute(): string
    {
        $url = 'https://wa.me/'.preg_replace('/\D/', '', $this->phone);

        if ($this->message) {
            $url .= '?text='.rawurlencode($this->message);
        }

        return $url;
    }

    /**
     * The link that's actually shared/copied (screenshot 79's "Generated
     * Link" field) — routes through ShortLinkRedirectController so `clicks`
     * counts real visits before bouncing to wa_me_url.
     */
    public function getShortUrlAttribute(): string
    {
        return rtrim(config('app.url'), '/')."/wa/{$this->slug}";
    }
}
