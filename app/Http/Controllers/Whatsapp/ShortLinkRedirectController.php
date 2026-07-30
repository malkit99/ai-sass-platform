<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappShortLink;
use Illuminate\Http\RedirectResponse;

/**
 * Public, unauthenticated redirect for a generated short link (screenshot
 * 79) — this is the URL people actually click (bio, ad, QR code), so it has
 * to work with no session. Tenant-scoped via `withoutGlobalScopes()` since
 * `BelongsToTenant`'s scope only no-ops for a guest anyway, but being
 * explicit here matches the same pattern WebhookController/SendAutoReplyJob
 * already use for out-of-session lookups.
 */
class ShortLinkRedirectController extends Controller
{
    public function __invoke(string $slug): RedirectResponse
    {
        $link = WhatsappShortLink::withoutGlobalScopes()->where('slug', $slug)->firstOrFail();

        $link->increment('clicks');

        return redirect()->away($link->wa_me_url);
    }
}
