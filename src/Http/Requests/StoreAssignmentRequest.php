<?php

namespace Mainul\UuiRolePermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['role' => 'required|string', 'tenant_id' => 'nullable|string', 'expires_at' => 'nullable|date|after:now', 'reason' => 'nullable|string|max:2000'];
    }
}
