<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
    }

    private function ensureAdmin(): void
    {
        $user = auth('api')->user();
        if (! $user || ! $user->is_admin) {
            abort(403, 'Acesso negado');
        }
    }

    private function userPayload(User $u): array
    {
        return [
            'id' => (int) $u->id,
            'name' => (string) $u->name,
            'email' => (string) $u->email,
            'is_active' => (bool) $u->is_active,
            'is_admin' => (bool) $u->is_admin,
            'created_at' => optional($u->created_at)->toJSON(),
            'updated_at' => optional($u->updated_at)->toJSON(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $q = trim((string) $request->query('q', $request->query('search', '')));
        $page = max(1, $request->integer('page', 1));
        $perPage = ($request->integer('per_page', $request->integer('perPage', $request->integer('pageSize', 10))));
        $perPage = max(1, min(100, $perPage));

        $sort = $request->query('sort', 'id');
        $dir = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'name', 'email', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'id';
        }

        $query = User::query()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $dir);

        $p = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'last_page' => $p->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'confirmed', Password::min(8)],
                'is_active' => ['boolean'],
            ],
            [
                'name.required' => 'Informe o nome.',
                'email.required' => 'Informe o e-mail.',
                'email.email' => 'E-mail inválido.',
                'email.unique' => 'Já existe um usuário com este e-mail.',
                'password.required' => 'Informe a senha.',
                'password.min' => 'A senha deve ter no mínimo :min caracteres.',
                'password.confirmed' => 'As senhas não conferem.',
                'password.letters' => 'A senha deve conter pelo menos uma letra.',
                'password.numbers' => 'A senha deve conter pelo menos um número.',
            ],
            [
                'name' => 'nome',
                'email' => 'e-mail',
                'password' => 'senha',
                'password_confirmation' => 'confirmação de senha',
            ]
        );

        $u = new User;
        $u->name = $data['name'];
        $u->email = $data['email'];
        $u->password = Hash::make($data['password']);
        $u->is_active = (bool) ($data['is_active'] ?? true);
        $u->is_admin = true; // mantém o comportamento original
        $u->save();

        return response()->json(
            $this->userPayload($u),
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin();

        $u = User::findOrFail($id);

        $data = $request->validate(
            [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u->id)],
                'password' => ['nullable', 'confirmed', Password::min(8)],
                'is_active' => ['sometimes', 'boolean'],
            ],
            [
                'name.required' => 'Informe o nome.',
                'email.required' => 'Informe o e-mail.',
                'email.email' => 'E-mail inválido.',
                'email.unique' => 'Já existe um usuário com este e-mail.',
                'password.min' => 'A senha deve ter no mínimo :min caracteres.',
                'password.confirmed' => 'As senhas não conferem.',
                'password.letters' => 'A senha deve conter pelo menos uma letra.',
                'password.numbers' => 'A senha deve conter pelo menos um número.',
            ],
            [
                'name' => 'nome',
                'email' => 'e-mail',
                'password' => 'senha',
                'password_confirmation' => 'confirmação de senha',
            ]
        );

        if (array_key_exists('name', $data)) {
            $u->name = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $u->email = $data['email'];
        }
        if (! empty($data['password'])) {
            $u->password = Hash::make($data['password']);
        }
        if (array_key_exists('is_active', $data)) {
            $u->is_active = (bool) $data['is_active'];
        }

        $u->save();

        return response()->json(
            $this->userPayload($u),
            200
        );
    }

    public function updatePassword(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin();

        $u = User::findOrFail($id);

        $data = $request->validate(
            [
                'password' => ['required', 'confirmed', Password::min(8)],
            ],
            [
                'password.required' => 'Informe a senha.',
                'password.min' => 'A senha deve ter no mínimo :min caracteres.',
                'password.confirmed' => 'As senhas não conferem.',
                'password.letters' => 'A senha deve conter pelo menos uma letra.',
                'password.numbers' => 'A senha deve conter pelo menos um número.',
            ],
            [
                'password' => 'nova senha',
                'password_confirmation' => 'confirmação de senha',
            ]
        );

        $u->password = Hash::make($data['password']);
        $u->save();

        return response()->json(['message' => 'Senha atualizada.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin();

        $admin = auth('api')->user();
        if ($admin && $admin->id === $id) {
            return response()->json([
                'message' => 'Não é possível excluir o próprio usuário.',
                'errors' => ['id' => ['Não é possível excluir o próprio usuário.']],
            ], 422);
        }

        $u = User::findOrFail($id);
        $u->delete();

        return response()->json(['message' => 'Excluído.']);
    }
}
