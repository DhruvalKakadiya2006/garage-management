<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterVehicleCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => 'nullable|in:name,models_count',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'sort.in' => 'Invalid sort column.',
            'direction.in' => 'Sort direction must be asc or desc.',
        ];
    }
}
