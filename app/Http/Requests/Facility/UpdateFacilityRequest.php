<?php

namespace App\Http\Requests\Facility;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $facilityId = $this->route('facility');

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('facilities', 'slug')->ignore($facilityId)
            ],
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'type' => 'required|in:general,room,hotel,bathroom,kitchen',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Название удобства обязательно для заполнения',
            'slug.required' => 'Slug обязательно для заполнения',
            'slug.unique' => 'Такой slug уже существует',
            'type.in' => 'Неверный тип удобства',
        ];
    }
}
