<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate user with email or phone + password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'nullable|string',
            'phone' => 'nullable|string',
            'login' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'required|string',
        ]);

        $identifier = $request->input('email')
            ?? $request->input('phone')
            ?? $request->input('login')
            ?? $request->input('username');

        if (blank($identifier)) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى إدخال البريد الإلكتروني أو رقم الهاتف (Email or phone number is required).',
            ], 422);
        }

        // Find user by email, phone, or username
        $user = User::with('school')
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('phone', $identifier)
                    ->orWhere('username', $identifier);
            })
            ->first();

        // If not found by direct user columns, check guardian table by phone or email
        if (! $user) {
            $guardian = Guardian::where('phone', $identifier)
                ->orWhere('email', $identifier)
                ->first();

            if ($guardian) {
                $user = User::with('school')
                    ->where(function ($query) use ($guardian) {
                        $query->where('guardian_id', $guardian->id)
                            ->orWhere(function ($q2) use ($guardian) {
                                $q2->where('school_id', $guardian->school_id)
                                    ->where(function ($q3) use ($guardian) {
                                        if ($guardian->email) {
                                            $q3->where('email', $guardian->email);
                                        }
                                        if ($guardian->phone) {
                                            $q3->orWhere('phone', $guardian->phone);
                                        }
                                    });
                            });
                    })
                    ->first();
            }
        }

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الاعتماد غير صحيحة (Invalid credentials).',
            ], 401);
        }

        // Verify active user status
        if ($user->is_active === false) {
            return response()->json([
                'success' => false,
                'message' => 'الحساب غير مفعّل، يرجى مراجعة إدارة المؤسسة (User account is inactive).',
            ], 403);
        }

        // Verify active school tenant status
        if ($user->school && $user->school->is_active === false) {
            return response()->json([
                'success' => false,
                'message' => 'المؤسسة التعليمية غير مفعلة حالياً (School tenant is currently inactive).',
            ], 403);
        }

        // Issue Sanctum token with user role and capabilities
        $role = $user->role ?? 'user';
        $token = $user->createToken('mobile_app_token', [$role])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح (Logged in successfully).',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUserProfile($user),
        ]);
    }

    /**
     * Revoke active user token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح (Logged out successfully).',
        ]);
    }

    /**
     * Return current user profile with tenant school details.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('school');

        return response()->json([
            'success' => true,
            'user' => $this->formatUserProfile($user),
        ]);
    }

    /**
     * Format user data for response.
     */
    protected function formatUserProfile(User $user): array
    {
        $school = $user->school;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'username' => $user->username,
            'role' => $user->role,
            'locale' => $user->locale ?? 'ar',
            'school_id' => $user->school_id,
            'guardian_id' => $user->guardian_id,
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'slug' => $school->slug,
                'email' => $school->email,
                'phone' => $school->phone,
                'is_active' => (bool) $school->is_active,
                'logo_url' => $school->logo_path ? asset('storage/'.$school->logo_path) : null,
            ] : null,
        ];
    }
}
