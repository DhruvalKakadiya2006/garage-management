<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterVehicleBrandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model_count' => 'nullable|in:all,has_models,no_models',
            'sort' => 'nullable|in:name,models_count',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'model_count.in' => 'Invalid model count filter.',
            'sort.in' => 'Invalid sort column.',
            'direction.in' => 'Sort direction must be asc or desc.',
        ];
    }
}
