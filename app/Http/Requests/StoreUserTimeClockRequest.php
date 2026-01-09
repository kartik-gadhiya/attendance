<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserTimeClockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'clock_date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i:s'],
            'shift_start' => ['nullable', 'date_format:H:i:s'],
            'shift_end' => ['nullable', 'date_format:H:i:s'],
            'type' => ['required', 'string', 'max:255', 'in:day_in,day_out,break_start,break_end'],
            'comment' => ['nullable', 'string'],
            'buffer_time' => ['nullable', 'integer'],
            'created_from' => ['nullable', 'string', 'size:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop_id.required' => 'Shop ID is required',
            'shop_id.integer' => 'Shop ID must be an integer',
            'user_id.exists' => 'The selected user does not exist',
            'clock_date.required' => 'Clock date is required',
            'clock_date.date' => 'Clock date must be a valid date',
            'time.required' => 'Time is required',
            'time.date_format' => 'Time must be in H:i:s format',
            'type.required' => 'Type is required',
            'type.in' => 'Type must be one of: day_in, day_out, break_start, break_end',
        ];
    }
}
