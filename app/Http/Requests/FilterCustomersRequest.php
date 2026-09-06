<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'outstanding' => 'nullable|in:all,has_outstanding,no_outstanding',
            'sort' => 'nullable|in:display_name,outstanding,total_visits,created_at',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'outstanding.in' => 'Invalid outstanding balance filter.',
            'sort.in' => 'Invalid sort column.',
            'direction.in' => 'Sort direction must be asc or desc.',
        ];
    }
}
