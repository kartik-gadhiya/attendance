<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'clock_date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'shift_start' => ['nullable', 'date_format:H:i'],
            'shift_end' => ['nullable', 'date_format:H:i'],
            'type' => ['required', 'string', 'max:255', 'in:day_in,day_out,break_start,break_end'],
            'comment' => ['nullable', 'string'],
            'buffer_time' => ['nullable', 'integer', 'min:1', 'max:24'],
            'created_from' => ['nullable', 'string', 'size:1'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
