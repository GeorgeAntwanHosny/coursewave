<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is the CommentPolicy, checked in the controller
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            // Existence/scope of the parent is validated in CreateCommentAction
            // (it must be a live top-level comment on THIS lesson).
            'parent_id' => ['nullable', 'integer'],
        ];
    }
}
