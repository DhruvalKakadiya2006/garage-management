<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterCounterInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_date' => 'nullable|date|required_with:to_date|before_or_equal:to_date',
            'to_date' => 'nullable|date|required_with:from_date|after_or_equal:from_date',
            'payment_status' => 'nullable|in:all,paid,partially_paid,unpaid',
            'customer_type' => 'nullable|in:all,registered,walk_in',
            'sort' => 'nullable|in:invoice_date,invoice_no,grand_total,payment_status_label',
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
            'customer_type.in' => 'Invalid customer type filter.',
        ];
    }
}
