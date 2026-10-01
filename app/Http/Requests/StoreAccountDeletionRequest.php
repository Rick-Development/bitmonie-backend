<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreAccountDeletionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone_number' => ['required', 'string', 'min:7', 'max:32', 'regex:/^\+?[0-9()\-\s]+$/'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'size:0'],
        ];
    }

    public function messages()
    {
        return [
            'website.size' => 'Invalid submission.',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'full_name' => $this->sanitizeText($this->input('full_name')),
            'email' => $this->sanitizeEmail($this->input('email')),
            'phone_number' => $this->sanitizePhone($this->input('phone_number')),
            'reason' => $this->sanitizeReason($this->input('reason')),
            'website' => $this->sanitizeText($this->input('website')),
        ]);
    }

    private function sanitizeText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strip_tags((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private function sanitizeEmail($value): ?string
    {
        $value = $this->sanitizeText($value);

        return $value === null ? null : Str::lower($value);
    }

    private function sanitizePhone($value): ?string
    {
        $value = $this->sanitizeText($value);

        return $value === null ? null : preg_replace('/\s+/u', ' ', $value);
    }

    private function sanitizeReason($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strip_tags((string) $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return trim($value);
    }
}
