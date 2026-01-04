<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|min:3|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
            'phone_number' => 'required|string|max:20|unique:users,phone_number',
            'bio' => 'sometimes|nullable|string|max:500',
            'birth_date' => 'required|date|before:-13 years',
            'gender' => 'required|in:male,female',
            'image' => 'sometimes|nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم الكامل مطلوب',
            'full_name.min' => 'الاسم يجب أن يكون على الأقل 3 أحرف',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.confirmed' => 'كلمات المرور غير متطابقة',
            'password.min' => 'كلمة المرور يجب أن تكون على الأقل 8 أحرف',
            'phone_number.required' => 'رقم الهاتف مطلوب',
            'phone_number.unique' => 'رقم الهاتف مستخدم مسبقاً',
            'birth_date.required' => 'تاريخ الميلاد مطلوب',
            'birth_date.before' => 'يجب أن يكون عمرك 13 سنة على الأقل',
            'gender.required' => 'الجنس مطلوب',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
        ];
    }
}