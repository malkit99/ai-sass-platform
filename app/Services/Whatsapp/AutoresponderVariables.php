<?php

namespace App\Services\Whatsapp;

/**
 * Resolves the %phone%/%date%/%time%/%random_number% placeholders an
 * autoresponder reply supports (screenshot 78's variable hint) — distinct
 * from the {{name}}/{{phone}} contact-lookup syntax used elsewhere
 * (TemplateVariables), since an auto-reply only ever has the inbound
 * sender's phone number to go on, not a resolved contact record.
 */
class AutoresponderVariables
{
    public static function render(?string $text, string $phone): ?string
    {
        if (! $text) {
            return $text;
        }

        return strtr($text, [
            '%phone%' => $phone,
            '%date%' => now()->format('Y-m-d'),
            '%time%' => now()->format('H:i'),
            '%random_number%' => (string) random_int(1000, 9999),
        ]);
    }
}
