<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\Suggestion;
use App\Rules\YouTubeInput;
use App\Services\YouTubeScraperService;
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
        $page = max(1, $request->integer('page', 1));

        $perPage = $request->integer(
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

    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $song = Song::findOrFail($id);
        $song->title = $data['title'];
        $song->save();

        return response()->json([
            'id' => $song->id,
            'youtube_id' => $song->youtube_id,
            'title' => $song->title,
            'plays' => (int) $song->plays,
        ]);
    }

    public function storeFromYoutube(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'youtube' => ['required', new YouTubeInput],
        ]);

        $svc = app(YouTubeScraperService::class);
        $meta = $svc->fetch($data['youtube']);

        if (! $meta || empty($meta['id'])) {
            return response()->json([
                'message' => 'Não foi possível obter dados do vídeo do YouTube.',
            ], 422);
        }

        $youtubeId = (string) $meta['id'];
        $title = (string) ($meta['title'] ?? '');
        $views = (int) ($meta['views'] ?? 0);

        if (Song::where('youtube_id', $youtubeId)->exists()) {
            return response()->json([
                'message' => 'Esta música já está cadastrada.',
                'errors' => [
                    'youtube' => ['Vídeo já existente na coleção.'],
                ],
            ], 409);
        }

        $song = Song::firstOrNew(['youtube_id' => $youtubeId]);
        if (! $song->exists) {
            $song->fill([
                'title' => $title,
                'plays' => $views,
            ]);
        } else {
            if (empty($song->title) && $title) {
                $song->title = $title;
            }
            $song->plays = max((int) ($song->plays ?? 0), $views);
        }
        $song->save();

        $sugg = Suggestion::firstOrNew(['youtube_id' => $youtubeId]);
        $sugg->fill([
            'title' => $title,
            'status' => 'approved',
            'view_count' => $views,
            'song_id' => $song->id,
            'removed_at' => null,
            'removed_reason' => null,
        ]);
        $sugg->reviewer_id = auth('api')->id();
        $sugg->reviewed_at = now();
        $sugg->save();

        return response()->json([
            'song' => ['id' => $song->id, 'youtube_id' => $song->youtube_id, 'title' => $song->title, 'plays' => (int) $song->plays],
            'suggestion' => ['id' => $sugg->id, 'status' => $sugg->status],
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureAdmin();

        $song = Song::findOrFail($id);
        $song->delete();

        return response()->json(['ok' => true]);
    }
}
