<?php

namespace Mainul\UuiRolePermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['reason' => 'nullable|string|max:2000', 'reassign_to' => 'nullable|string'];
    }
}
