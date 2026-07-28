<?php

namespace App\Services\Whatsapp;

/**
 * Resolves spintax message randomization, e.g. "{Hi|Hello|Hola} there" ->
 * one random pick per {...} group — used by bulk campaigns (and reused by
 * autoresponder/chatbot replies) so every recipient doesn't get a byte-identical
 * message, per the reference app's "Random message by Spintax" feature.
 */
class Spintax
{
    /**
     * Innermost-first so nested groups like "{a|{b|c}}" resolve correctly —
     * the regex only ever matches a {...} with no nested braces inside it,
     * so repeated passes peel one level at a time until none remain.
     */
    public static function render(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        while (preg_match('/\{([^{}]+)\}/', $text, $match)) {
            $options = explode('|', $match[1]);
            $text = substr_replace($text, $options[array_rand($options)], strpos($text, $match[0]), strlen($match[0]));
        }

        return $text;
    }
}
