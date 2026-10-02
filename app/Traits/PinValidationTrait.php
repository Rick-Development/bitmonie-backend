<?php

namespace App\Traits;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Exception;

trait PinValidationTrait
{
    /**
     * Validate Transaction PIN (4 digits)
     * Supports both Hashed and Legacy Plain-text PINs
     * @param mixed $user
     * @param string $pin
     * @return bool
     */
    public function validateTransactionPin($user, $pin)
    {
        // 1. Basic format check
        if (!preg_match('/^\d{4}$/', $pin)) {
            return false;
        }

        // 2. Check if hashed match
        if (Hash::check($pin, $user->pin_code)) {
            return true;
        }

        // 3. Fallback: Check plain text (Legacy) and upgrade if match
        if ($user->pin_code === $pin) {
            try {
                $user->pin_code = Hash::make($pin);
                $user->save();
            } catch (Exception $e) {
                // Fail silently on save, but allow validation pass
            }
            return true;
        }

        return false;
    }

    /**
     * Validate Login PIN (6 digits)
     * @param mixed $user
     * @param string $pin
     * @return bool
     */
    public function validateLoginPin($user, $pin)
    {
        // 1. Basic format check
        if (!preg_match('/^\d{6}$/', $pin)) {
            return false;
        }

        // 2. Check Hash
        if (Hash::check($pin, $user->login_pin)) {
            return true;
        }

        // 3. Fallback: Plain text just in case (though new feature)
        if ($user->login_pin === $pin) {
            try {
                $user->login_pin = Hash::make($pin);
                $user->save();
            } catch (Exception $e) {
                // Fail silently
            }
            return true;
        }

        return false;
    }

    /**
     * Validate if Transaction PIN is setup
     */
    public function hasTransactionPin($user) {
        return $user->pin_status && !empty($user->pin_code);
    }

    /**
     * Validate if Login PIN is setup
     */
    public function hasLoginPin($user) {
        return !empty($user->login_pin);
    }
}
