<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'role' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'name.min' => 'Họ tên cần có ít nhất 2 ký tự.',
            'email.email' => 'Email chưa đúng định dạng.',
            'email.unique' => 'Email này đã được đăng ký.',
            'password.min' => 'Mật khẩu cần có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận chưa khớp.',
            'role.prohibited' => 'Không được tự chỉ định vai trò khi đăng ký.',
        ];
    }
}
