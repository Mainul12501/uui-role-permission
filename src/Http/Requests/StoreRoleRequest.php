<?php

namespace Mainul\UuiRolePermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['code' => 'required|string|max:120|regex:/^[A-Z0-9_.-]+$/', 'name' => 'required|string|max:160', 'description' => 'nullable|string', 'assignable_scope' => 'sometimes|in:GLOBAL,TENANT,ANY', 'priority' => 'sometimes|integer', 'reason' => 'nullable|string|max:2000'];
    }
}
