<?php

namespace Mainul\UuiRolePermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => 'sometimes|required|string|max:160', 'description' => 'nullable|string', 'assignable_scope' => 'sometimes|in:GLOBAL,TENANT,ANY', 'priority' => 'sometimes|integer', 'expected_updated_at' => 'sometimes|required|date', 'reason' => 'nullable|string|max:2000'];
    }
}
