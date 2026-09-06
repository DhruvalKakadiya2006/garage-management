<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => 'nullable|in:all,'.implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'from_date' => 'nullable|date|required_with:to_date|before_or_equal:to_date',
            'to_date' => 'nullable|date|required_with:from_date|after_or_equal:from_date',
            'document_type' => 'nullable|in:all,job_card,counter_invoice',
            'sort' => 'nullable|in:payment_date,amount,payment_mode',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.required_with' => 'Both From Date and To Date must be provided.',
            'to_date.required_with' => 'Both From Date and To Date must be provided.',
            'payment_mode.in' => 'Invalid payment mode.',
            'document_type.in' => 'Invalid document type.',
        ];
    }
}
