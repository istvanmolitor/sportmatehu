<?php

namespace App\Http\Requests;

use App\Enums\SyncTargetType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSyncTargetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(SyncTargetType::class)],
        ];
    }

    /**
     * The uniqueness requirement here is scoped to the authenticated user:
     * the global sync target may already exist (added by someone else), but
     * the current user must not have already added it to their own list.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $alreadyAdded = $this->user()->syncTargets()
                ->where('name', $this->input('name'))
                ->where('type', $this->input('type'))
                ->exists();

            if ($alreadyAdded) {
                $validator->errors()->add('name', __('You have already added this GitHub user or organization.'));
            }
        });
    }
}
