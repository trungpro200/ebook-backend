<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ]);

        $user = $request->user();
        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('avatar_url', $validated)) {
            if ($user->avatar && ! Str::startsWith($user->avatar, ['http://', 'https://'])) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $validated['avatar_url'];
        }
        $user->save();

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'font_size' => ['sometimes', 'integer', 'min:14', 'max:32'],
            'line_height' => ['sometimes', 'numeric', 'min:1.2', 'max:2.5'],
            'theme' => ['sometimes', 'in:light,sepia,dark'],
            'speed' => ['sometimes', 'numeric', 'in:0.75,1,1.25,1.5,2'],
        ]);

        $user = $request->user();
        $user->reader_preferences = array_merge($user->reader_preferences ?? [], $validated);
        $user->save();

        return response()->json(['preferences' => $user->reader_preferences]);
    }

    /** @return array{id: int, name: string, email: string, role: string, avatar_url: ?string, preferences: array<string, mixed>} */
    public static function userPayload(User $user): array
    {
        $avatarUrl = $user->avatar;
        if ($avatarUrl !== null && ! Str::startsWith($avatarUrl, ['http://', 'https://'])) {
            $avatarUrl = url(Storage::disk('public')->url($avatarUrl));
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar_url' => $avatarUrl,
            'preferences' => $user->reader_preferences ?? [],
        ];
    }
}
