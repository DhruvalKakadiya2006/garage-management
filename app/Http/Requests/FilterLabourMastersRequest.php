<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterLabourMastersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'taxable' => 'nullable|in:all,taxable,non_taxable',
            'sort' => 'nullable|in:service_name,rate,taxable',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'taxable.in' => 'Invalid taxability filter.',
            'sort.in' => 'Invalid sort column.',
            'direction.in' => 'Sort direction must be asc or desc.',
        ];
    }
}
