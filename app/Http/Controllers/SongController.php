<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SongController extends Controller
{
    public function top(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 5);

        $data = Song::query()
            ->orderByDesc('plays')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }

    public function rest(Request $request): JsonResponse
    {
        $offsetTop = (int) $request->query('top', 5);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

        $totalAll = Song::query()->count();
        $total = max(0, $totalAll - $offsetTop);
        $skip = $offsetTop + ($page - 1) * $perPage;

        $data = Song::query()
            ->orderByDesc('plays')
            ->skip($skip)
            ->take($perPage)
            ->get();

        return response()->json([
            'data' => $data,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($skip + $perPage) < ($offsetTop + $total),
        ]);
    }
}
