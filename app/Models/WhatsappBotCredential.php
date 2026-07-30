<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * An AI provider connection (API key) a flow author enters once and reuses
 * across any number of "AI Reply" nodes — never a platform-held key, always
 * the bot builder's own. api_key is never exposed via serialization
 * ($hidden) — the frontend credential picker only ever needs id/provider/
 * label; the raw key is read server-side only, inside BotFlowExecutor.
 */
class WhatsappBotCredential extends Model
{
    use BelongsToTenant;

    public const PROVIDERS = ['openai', 'anthropic', 'groq', 'deepseek', 'together', 'openrouter', 'mistral', 'perplexity'];

    protected $fillable = ['account_id', 'provider', 'label', 'api_key'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }
}
