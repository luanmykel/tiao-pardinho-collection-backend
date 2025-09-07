<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SongAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
    }

    private function ensureAdmin(): void
    {
        $user = auth('api')->user();
        abort_if(! $user || ! $user->is_admin, 403, 'Acesso negado');
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $q = trim((string) $request->query('q', $request->query('search', '')));
        $page = max(1, (int) $request->integer('page', 1));

        $perPage = (int) $request->integer(
            'per_page',
            $request->integer('perPage', $request->integer('pageSize', 10))
        );
        $perPage = max(1, min(100, $perPage));

        $sort = (string) $request->query('sort', 'id');
        $dir = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'title', 'artist', 'plays', 'created_at', 'updated_at'];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'id';
        }

        $query = Song::query()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%{$q}%")
                        ->orWhere('artist', 'like', "%{$q}%");
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

    public function destroy(int $id): JsonResponse
    {
        $this->ensureAdmin();

        $song = Song::findOrFail($id);
        $song->delete();

        return response()->json(['ok' => true]);
    }
}
