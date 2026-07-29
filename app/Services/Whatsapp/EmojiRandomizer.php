<?php

namespace App\Services\Whatsapp;

/**
 * Appends a random emoji to a message body — "Enable Emoji Randomizer"
 * (screenshot 76), a lightweight per-recipient variation used alongside
 * spintax so bulk-campaign messages aren't byte-identical across recipients.
 */
class EmojiRandomizer
{
    private const EMOJIS = ['😊', '👍', '🙂', '✅', '🔥', '💬', '📩', '✨', '🎉', '👋', '💯', '🙌'];

    public static function append(?string $text): ?string
    {
        if (! $text) {
            return $text;
        }

        return $text.' '.self::EMOJIS[array_rand(self::EMOJIS)];
    }
}
