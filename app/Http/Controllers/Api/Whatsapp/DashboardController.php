<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\WhatsappAutoresponder;
use App\Models\WhatsappChatbotRule;
use App\Models\WhatsappCreditBalance;
use Illuminate\Support\Facades\Auth;

/**
 * Backs the WhatsApp module's landing dashboard (stat cards, message
 * distribution, performance summary — see screenshot 30 / 11-unofficial-whatsapp.md).
 * `sent_by` on an outbound message is how a send's origin is told apart:
 * null/user id = direct single send, "campaign:{id}" = bulk, "auto_reply" = chatbot/autoresponder.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $account = Auth::user()->account;
        $balance = WhatsappCreditBalance::forAccount($account);

        $outboundThisMonth = Message::query()
            ->where('direction', Message::DIRECTION_OUT)
            ->whereHas('conversation', fn ($q) => $q->where('account_id', $account->id))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get(['sent_by', 'status']);

        $direct = $outboundThisMonth->filter(fn ($m) => $m->sent_by === null || is_numeric($m->sent_by));
        $bulk = $outboundThisMonth->filter(fn ($m) => str_starts_with((string) $m->sent_by, 'campaign:'));
        $auto = $outboundThisMonth->filter(fn ($m) => $m->sent_by === 'auto_reply');

        $bulkDelivered = $bulk->where('status', 'sent')->count();
        $bulkTotal = $bulk->count();

        return response()->json([
            'credits' => [
                'remaining' => $balance->credits_remaining,
                'limit' => $account->plan?->limits['whatsapp_credits'] ?? null,
            ],
            'messages_sent_this_month' => $outboundThisMonth->count(),
            'bulk' => [
                'delivered' => $bulkDelivered,
                'success_rate' => $bulkTotal > 0 ? round($bulkDelivered / $bulkTotal * 100) : 100,
            ],
            'autoresponder_active_count' => WhatsappAutoresponder::query()->where('enabled', true)->count(),
            'chatbot_active_count' => WhatsappChatbotRule::query()->where('enabled', true)->count(),
            'message_distribution' => [
                'direct' => $direct->count(),
                'bulk' => $bulk->count(),
                'auto' => $auto->count(),
            ],
        ]);
    }
}
