<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\Suggestion;
use App\Services\YouTubeScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuggestionController extends Controller
{
    public function store(Request $request, YouTubeScraperService $yt): JsonResponse
    {
        $data = $request->validate([
            'youtube' => ['required', 'string'],
        ]);

        $id = $yt->extractId($data['youtube']);
        if (! $id) {
            return response()->json([
                'message' => 'O campo youtube deve ser uma URL ou ID válido do YouTube.',
                'errors' => ['youtube' => ['ID/URL inválido.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (Song::query()->where('youtube_id', $id)->exists()) {
            return response()->json([
                'code' => 'already_in_songs',
                'message' => 'Essa música já está cadastrada em nossa base de dados.',
            ], Response::HTTP_CONFLICT);
        }

        $existing = Suggestion::query()->where('youtube_id', $id)->latest('id')->first();
        if ($existing) {
            switch ($existing->status) {
                case 'pending':
                    return response()->json([
                        'code' => 'already_suggested_pending',
                        'message' => 'Já recebemos essa sugestão e ela está em análise. Obrigado por aguardar!',
                    ], Response::HTTP_CONFLICT);
                case 'approved':
                    break;
                case 'rejected':
                    return response()->json([
                        'code' => 'already_suggested_rejected',
                        'message' => 'Essa sugestão foi recusada. Se desejar incluí-la, entre em contato conosco.',
                    ], Response::HTTP_CONFLICT);
            }
        }

        $meta = $yt->fetchById($id);
        $title = $meta['title'] ?? null;
        $views = (int) ($meta['views'] ?? 0);

        $s = Suggestion::query()->create([
            'youtube_id' => $id,
            'title' => $title,
            'view_count' => $views,
            'status' => 'pending',
        ]);

        return response()->json([
            'id' => $s->id,
            'youtube_id' => $s->youtube_id,
            'title' => $s->title,
            'view_count' => $s->view_count,
            'status' => $s->status,
            'created_at' => $s->created_at,
            'updated_at' => $s->updated_at,
        ], Response::HTTP_CREATED);
    }

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', $request->query('search', '')));
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(100, (int) $request->integer('per_page', 10)));
        $status = $request->query('status');          // ex: "pending"
        $statusIn = $request->query('status_in');       // ex: "approved,rejected"
        $sort = (string) $request->query('sort', 'id');
        $dir = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'id',
            'title',
            'view_count',
            'reviewed_at',
            'status',
            'youtube_id',
            'created_at',
        ];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'id';
        }

        $query = Suggestion::query();

        if ($status) {
            $query->where('status', $status);
        } elseif ($statusIn) {
            $set = array_filter(array_map('trim', explode(',', (string) $statusIn)));
            if (! empty($set)) {
                $query->whereIn('status', $set);
            }
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('youtube_id', 'like', "%{$q}%");
            });
        }

        if ($sort === 'view_count') {
            $query->orderByRaw('view_count IS NULL')
                ->orderBy('view_count', $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $data = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($data);
    }
}
