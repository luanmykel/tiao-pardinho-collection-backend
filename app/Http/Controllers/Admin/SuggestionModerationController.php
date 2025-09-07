<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\Suggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuggestionModerationController extends Controller
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

        $term = trim((string) $request->query('q', $request->query('search', '')));
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(100, (int) $request->integer('per_page', 10)));
        $status = $request->query('status');          // e.g. "pending"
        $statusIn = $request->query('status_in');       // e.g. "approved,rejected"
        $sort = (string) $request->query('sort', 'id');
        $dir = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortable = ['id', 'title', 'view_count', 'reviewed_at', 'status', 'youtube_id', 'created_at'];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'id';
        }

        $q = Suggestion::query();

        if ($status) {
            $q->where('status', $status);
        }

        if ($statusIn) {
            $set = array_filter(array_map('trim', explode(',', (string) $statusIn)));
            if (! empty($set)) {
                $q->whereIn('status', $set);
            }
        }

        if ($term !== '') {
            $q->where(function ($sub) use ($term) {
                $sub->where('title', 'like', "%{$term}%")
                    ->orWhere('youtube_id', 'like', "%{$term}%");
            });
        }

        if ($sort === 'view_count') {
            $q->orderByRaw('view_count IS NULL')->orderBy('view_count', $dir);
        } else {
            $q->orderBy($sort, $dir);
        }

        $p = $q->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $p->getCollection()->map(function (Suggestion $s) {
                return [
                    'id' => $s->id,
                    'youtube_id' => $s->youtube_id,
                    'title' => $s->title,
                    'status' => $s->status,
                    'view_count' => (int) $s->view_count,
                    'reviewer_id' => $s->reviewer_id,
                    'reviewed_at' => $s->reviewed_at?->toISOString(),
                    'song_id' => $s->song_id,
                    'removed_at' => $s->removed_at?->toISOString(),
                    'removed_reason' => $s->removed_reason,
                    'created_at' => $s->created_at?->toISOString(),
                    'updated_at' => $s->updated_at?->toISOString(),
                ];
            })->all(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'last_page' => $p->lastPage(),
            ],
        ]);
    }

    public function approve(Suggestion $suggestion): JsonResponse
    {
        $this->ensureAdmin();

        DB::transaction(function () use ($suggestion) {
            $playsFromSuggestion = (int) ($suggestion->view_count ?? 0);

            $song = Song::firstOrNew(['youtube_id' => $suggestion->youtube_id]);

            if (! $song->exists) {
                $song->fill([
                    'title' => $suggestion->title ?? '',
                    'plays' => $playsFromSuggestion,
                ]);
            } else {
                if (empty($song->title) && ! empty($suggestion->title)) {
                    $song->title = $suggestion->title;
                }
                $song->plays = max((int) ($song->plays ?? 0), $playsFromSuggestion);
            }

            $song->save();

            $suggestion->song_id = $song->id;
            $suggestion->status = 'approved';
            $suggestion->reviewer_id = auth('api')->id();
            $suggestion->reviewed_at = now();
            $suggestion->removed_at = null;
            $suggestion->removed_reason = null;
            $suggestion->save();
        });

        return response()->json([
            'id' => $suggestion->id,
            'youtube_id' => $suggestion->youtube_id,
            'title' => $suggestion->title,
            'status' => $suggestion->status,
            'view_count' => (int) $suggestion->view_count,
        ]);
    }

    public function reject(Suggestion $suggestion): JsonResponse
    {
        $this->ensureAdmin();

        if ($suggestion->status !== 'rejected') {
            $suggestion->forceFill([
                'status' => 'rejected',
                'reviewer_id' => auth('api')->id(),
                'reviewed_at' => now(),
            ])->save();
        }

        return response()->json([
            'id' => $suggestion->id,
            'youtube_id' => $suggestion->youtube_id,
            'title' => $suggestion->title,
            'status' => $suggestion->status,
            'view_count' => (int) $suggestion->view_count,
        ]);
    }
}
