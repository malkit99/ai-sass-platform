<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\WhatsappCallLog;
use App\Models\WhatsappCallResponderSetting;
use Illuminate\Http\Request;

class CallResponderController extends Controller
{
    public function settings(Request $request)
    {
        $this->authorize('viewAny', WhatsappCallResponderSetting::class);

        $channel = $this->resolveChannel($request);

        return WhatsappCallResponderSetting::query()->where('channel_id', $channel->id)->first()
            ?? new WhatsappCallResponderSetting([
                'channel_id' => $channel->id,
                'enabled' => false,
                'auto_reject_enabled' => true,
                'reply_delay_seconds' => 0,
            ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'enabled' => ['boolean'],
            'auto_reject_enabled' => ['boolean'],
            'reply_delay_seconds' => ['required', 'integer', 'min:0', 'max:120'],
            'missed_call_reply' => ['nullable', 'string', 'max:4096'],
            'after_call_reply' => ['nullable', 'string', 'max:4096'],
            'rejected_call_reply' => ['nullable', 'string', 'max:4096'],
            'missed_before_answer_reply' => ['nullable', 'string', 'max:4096'],
        ]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        $setting = WhatsappCallResponderSetting::query()->where('channel_id', $channel->id)->first();

        if ($setting) {
            $this->authorize('update', $setting);
            $setting->update($data);
        } else {
            $this->authorize('create', WhatsappCallResponderSetting::class);
            $setting = WhatsappCallResponderSetting::create([...$data, 'account_id' => $channel->account_id]);
        }

        return $setting;
    }

    public function history(Request $request)
    {
        $this->authorize('viewAny', WhatsappCallResponderSetting::class);

        $channel = $this->resolveChannel($request);

        return WhatsappCallLog::query()->where('channel_id', $channel->id)->latest('started_at')->limit(50)->get();
    }

    private function resolveChannel(Request $request): Channel
    {
        $data = $request->validate(['channel_id' => ['required', 'integer', 'exists:channels,id']]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        return $channel;
    }
}
