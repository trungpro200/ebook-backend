<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));

            return $this->authenticated($user, 201);
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();
        $passwordHash = $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

        if (! Hash::check($credentials['password'], $passwordHash) || ! $user) {
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng.']);
        }

        if (Hash::needsRehash($user->password)) {
            $user->password = $credentials['password'];
            $user->save();
        }

        return $this->authenticated($user);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->only(['id', 'name', 'email', 'role'])]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function authenticated(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('mocthu-app')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ], $status)->header('Cache-Control', 'no-store');
    }
}
