<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterSparePartsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'stock_status' => 'nullable|in:all,in_stock,out_of_stock',
            'category' => 'nullable|string',
            'sort' => 'nullable|in:part_name,stock_qty,mrp,brand,part_category',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'stock_status.in' => 'Invalid stock status filter.',
            'sort.in' => 'Invalid sort column.',
            'direction.in' => 'Sort direction must be asc or desc.',
        ];
    }
}
