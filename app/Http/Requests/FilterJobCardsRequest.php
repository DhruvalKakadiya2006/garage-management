<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterJobCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'from_date' => 'nullable|date|bail|required_with:to_date|before_or_equal:to_date',
            'to_date' => 'nullable|date|bail|required_with:from_date|after_or_equal:from_date',
            'technician' => ['nullable', Rule::exists('employees', 'id')],
            'status' => ['nullable', 'in:'.implode(',', array_keys(config('garage.option_lists.job_card_status')))],
            'brand' => ['nullable', Rule::exists('vehicle_brands', 'name')],
            'payment_status' => 'nullable|in:all,paid,partially_paid,unbilled,unpaid',
            'job_type' => ['nullable', 'in:'.implode(',', array_keys(config('garage.option_lists.job_type')))],
            'sort' => 'nullable|in:job_date,status,assigned_technician_id,grand_total,payment_status_label',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.required_with' => 'Both From Date and To Date must be provided.',
            'to_date.required_with' => 'Both From Date and To Date must be provided.',
            'from_date.before_or_equal' => 'From Date must be before or equal to To Date.',
            'to_date.after_or_equal' => 'To Date must be after or equal to From Date.',
            'payment_status.in' => 'Invalid payment status filter.',
            'job_type.in' => 'Invalid job type filter.',
        ];
    }
}
