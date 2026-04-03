<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTranslationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $translation = $this->route('translation');

        return [
            'locale' => ['sometimes', 'required', 'string', 'max:12', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                Rule::unique('translations')
                    ->ignore($translation?->id)
                    ->where(fn ($query) => $query->where('locale', $this->input('locale', $translation?->locale))),
            ],
            'value' => ['sometimes', 'required', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:64'],
        ];
    }
}
