<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required|string|email', // الآن فقط بالإيميل
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'البريد الإلكتروني مطلوب',
            'login.email' => 'يرجى إدخال بريد إلكتروني صحيح',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون على الأقل 8 أحرف',
        ];
    }
}