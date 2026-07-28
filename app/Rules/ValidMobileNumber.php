<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

/**
 * Validates a phone number stored as digits-only with country code and no
 * leading "+" (e.g. "919876543210") — the exact format WhatsApp (Cloud API
 * and unofficial clients alike) expects to address a contact. Uses Google's
 * libphonenumber so a wrong-length or invalid-for-that-country number is
 * actually caught, not just "looks like some digits".
 */
class ValidMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            $phoneNumber = PhoneNumberUtil::getInstance()->parse('+'.$value, null);
        } catch (NumberParseException) {
            $fail('Enter a valid mobile number with country code (no + sign).');

            return;
        }

        if (! PhoneNumberUtil::getInstance()->isValidNumber($phoneNumber)) {
            $fail('Enter a valid mobile number with country code (no + sign).');
        }
    }
}
