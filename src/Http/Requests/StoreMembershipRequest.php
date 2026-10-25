<?php

namespace Mainul\UuiRolePermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['user_id' => 'required|string', 'status' => 'sometimes|string|max:24', 'reason' => 'nullable|string|max:2000'];
    }
}
