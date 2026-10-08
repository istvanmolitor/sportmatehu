<?php

namespace App\Http\Requests;

use App\Enums\SyncTargetType;
use App\Repositories\Contracts\SyncTargetRepositoryInterface;
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
     * @return array<int, callable>
     */
    public function after(SyncTargetRepositoryInterface $syncTargets): array
    {
        return [
            function (Validator $validator) use ($syncTargets) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $alreadyAdded = $syncTargets->userHasTarget(
                    $this->user(),
                    $this->string('name')->toString(),
                    $this->enum('type', SyncTargetType::class),
                );

                if ($alreadyAdded) {
                    $validator->errors()->add('name', __('Ezt a GitHub felhasználót vagy szervezetet már hozzáadtad.'));
                }
            },
        ];
    }
}
