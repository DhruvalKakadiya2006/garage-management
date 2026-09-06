<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'active' => 'nullable|in:all,active_only,inactive_only',
            'role' => 'nullable|in:all,'.implode(',', array_keys(config('garage.option_lists.employee_role'))),
            'specialization' => 'nullable|in:all,'.implode(',', array_keys(config('garage.option_lists.employee_specialization'))),
            'sort' => 'nullable|in:name,role,specialization,active',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'active.in' => 'Invalid active status filter.',
            'role.in' => 'Invalid role filter.',
            'specialization.in' => 'Invalid specialization filter.',
            'sort.in' => 'Invalid sort column.',
            'direction.in' => 'Sort direction must be asc or desc.',
        ];
    }
}
