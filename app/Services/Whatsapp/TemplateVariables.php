<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappContact;

/**
 * Replaces {{name}}, {{phone}}, and custom {{...}} placeholders in a message
 * body with real values. Bulk campaigns resolve values per-recipient from the
 * contact table (name + the JSON `params` column populated by CSV/Excel
 * import); single sends take user-typed values from the form's variable
 * inputs. Placeholders with no matching value render as an empty string
 * rather than leaking "{{name}}" to the recipient. Spintax groups ({a|b})
 * are untouched — this only matches double-brace word tokens.
 */
class TemplateVariables
{
    public static function render(?string $text, array $values): ?string
    {
        if (! $text) {
            return $text;
        }

        return preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/',
            fn ($match) => (string) ($values[$match[1]] ?? ''),
            $text,
        );
    }

    /** The standard value set a contact row provides for a given recipient. */
    public static function forContact(string $phone, ?WhatsappContact $contact): array
    {
        return [
            'phone' => $phone,
            'name' => $contact->name ?? '',
            ...($contact->params ?? []),
        ];
    }
}
