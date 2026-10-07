<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class RecordProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'watched_seconds' => ['nullable', 'integer', 'min:0', 'max:120', 'required_without:completed'],
            'completed' => ['nullable', 'boolean', 'required_without:watched_seconds'],
        ];
    }
}
