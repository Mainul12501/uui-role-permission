<?php

namespace Mainul\UuiRolePermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Mainul\UuiRolePermission\Support\Config;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['permissions' => 'required|array', 'permissions.*' => 'string|exists:'.Config::table('permissions').',code', 'reason' => 'nullable|string|max:2000'];
    }
}
