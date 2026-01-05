<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * تسجيل مستخدم جديد
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // إنشاء المستخدم بالحقول الجديدة
            $user = User::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->phone_number,
                'bio' => $request->bio ?? null,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'image' => $request->image ?? null, // اسم الحقل تغير من profile_pic_url إلى image
            ]);

            // إنشاء توكن API
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => $user->makeHidden(['password', 'remember_token']),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during recording',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * تسجيل الدخول (بالإيميل فقط الآن)
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // البحث عن المستخدم بالإيميل فقط (اليوزرنيم مش موجود)
            $user = User::where('email', $request->login)->first();

            // التحقق من وجود المستخدم وصحة كلمة المرور
            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'login' => ['Incorrect login details'],
                ]);
            }

            // إنشاء توكن جديد
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user->makeHidden(['password', 'remember_token']),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_at' => now()->addDays(7)->toDateTimeString()
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect login details',
                'errors' => $e->errors()
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while logging in',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while logging out',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * معلومات المستخدم الحالي
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load([
                'followers' => function ($query) {
                    $query->wherePivot('status', 'accepted');
                },
                'following' => function ($query) {
                    $query->wherePivot('status', 'accepted');
                },
                'posts' => function ($query) {
                    $query->latest()->limit(5);
                }
            ]);

            $stats = [
                'posts_count' => $user->posts()->count(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'likes_count' => $user->likes()->count(),
                'comments_count' => $user->comments()->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'User data has been retrieved',
                'data' => [
                    'user' => $user->makeHidden(['password', 'remember_token']),
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving user data.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * تحديث الملف الشخصي
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'phone_number' => 'sometimes|string|max:20|unique:users,phone_number,' . $user->id,
                'bio' => 'sometimes|nullable|string|max:500',
                'birth_date' => 'sometimes|date|before:-13 years',
                'gender' => 'sometimes|in:male,female',
                'image' => 'sometimes|nullable|url|max:500',
            ]);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user->fresh()->makeHidden(['password', 'remember_token'])
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect data',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the profile.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * تحديث كلمة المرور
     */
    public function updatePassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            $user = $request->user();

            // التحقق من كلمة المرور الحالية
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The current password is incorrect.'
                ], 422);
            }

            // تحديث كلمة المرور
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // حذف جميع التوكنات القديمة (لأمان أكثر)
            $user->tokens()->delete();

            // إنشاء توكن جديد
            $newToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully',
                'data' => [
                    'user' => $user->makeHidden(['password', 'remember_token']),
                    'token' => $newToken,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect data',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the password',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * استعادة كلمة المرور (نسيت كلمة المرور)
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // هنا يمكنك إرسال إيميل استعادة كلمة المرور
            // يمكنك استخدام Laravel Fortify أو Notification

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء استعادة كلمة المرور',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * تحديث صورة الملف الشخصي (رفع ملف مباشر)
     */

    /**
     * حذف حساب المستخدم
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'password' => 'required|string|min:8',
            ]);

            $user = $request->user();

            // التحقق من كلمة المرور قبل الحذف
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'كلمة المرور غير صحيحة'
                ], 422);
            }

            // حذف جميع التوكنات
            $user->tokens()->delete();
            
            // حذف المستخدم
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف حسابك بنجاح'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الحساب',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}