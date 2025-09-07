<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Usuário inativo'], 403);
        }

        $token = auth('api')->login($user);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'token' => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'is_active' => (bool) $user->is_active,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $u = auth('api')->user();

        return response()->json([
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'is_admin' => (bool) $u->is_admin,
            'is_active' => (bool) $u->is_active,
            'avatar_url' => $u->avatar_url,
        ]);
    }

    public function refresh(): JsonResponse
    {
        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => auth('api')->refresh(),
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'ok']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $u = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $u->fill($data)->save();

        return response()->json([
            'message' => 'Perfil atualizado',
            'user' => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_admin' => (bool) $u->is_admin,
                'is_active' => (bool) $u->is_active,
                'avatar_url' => $u->avatar_url,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $u = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $u->password)) {
            return response()->json([
                'message' => 'Senha atual inválida',
                'errors' => ['current_password' => ['Senha atual inválida']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $u->password = Hash::make($data['password']);
        $u->save();

        return response()->json(['message' => 'Senha alterada com sucesso']);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $u = $request->user();

        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:5120'],
        ]);

        if ($u->avatar_path) {
            $old = str_starts_with($u->avatar_path, 'public/')
                ? substr($u->avatar_path, 7)
                : $u->avatar_path;
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $u->avatar_path = $path;
        $u->save();

        return response()->json([
            'message' => 'Avatar atualizado',
            'avatar_url' => $u->avatar_url,
        ], 201);
    }

    public function clearAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Avatar removido com sucesso.',
            'user' => $user->fresh(),
        ]);
    }

    public function activeUsers(): JsonResponse
    {
        $users = User::query()
            ->active()
            ->select('id', 'name', 'email', 'avatar_path', 'is_admin', 'is_active')
            ->orderBy('name')
            ->paginate(15);

        $users->getCollection()->transform(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_admin' => (bool) $u->is_admin,
                'is_active' => (bool) $u->is_active,
                'avatar_url' => $u->avatar_url,
            ];
        });

        return response()->json($users);
    }
}
