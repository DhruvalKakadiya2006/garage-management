<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobCardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'odometer_reading_km' => 'nullable|integer|min:0',
            'job_type' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.job_type'))),
            'promised_delivery_date' => 'nullable|date|after_or_equal:job_date',
            'promised_delivery_time' => 'nullable|date_format:H:i',
            'remarks' => 'nullable|string',
            'complaints' => 'nullable|array|max:20',
            'complaints.*.description' => 'required|string',
        ];
    }
}
