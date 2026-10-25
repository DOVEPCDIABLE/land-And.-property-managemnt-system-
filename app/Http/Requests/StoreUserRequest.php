<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->hasPermission('users.manage');
    }

    public function rules(): array
    {
        $roles = collect(config('workspaces.roles'))->filter(fn ($r) => in_array('organisation', $r['allowed_kinds'] ?? [$r['kind']], true))->keys()->all();

        return ['name' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'max:120'], 'email' => [$this->isMethod('post') ? 'required' : 'nullable', 'email', 'max:255', ...($this->isMethod('post') ? ['unique:users'] : [])], 'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()], 'role_id' => ['required', Rule::exists('roles', 'id')->whereIn('name', $roles)], 'agency_id' => [$this->user()->isPlatformAdmin() && $this->isMethod('post') ? 'required' : 'nullable', 'exists:agencies,id'], 'is_active' => 'sometimes|boolean'];
    }
}
