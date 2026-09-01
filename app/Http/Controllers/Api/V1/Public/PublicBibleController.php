<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use App\Support\Bible\BibleCanon;
use App\Support\Bible\BibleReadingPlanGenerator;
use App\Support\Bible\BibleTextStore;
use App\Support\Bible\BibleVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PublicBibleController extends Controller
{
    public function versions(Request $request): JsonResponse
    {
        return ApiResponse::success($request, [
            'versions' => BibleVersions::catalog(),
            'default' => BibleVersions::KJV,
        ]);
    }

    public function books(Request $request): JsonResponse
    {
        $version = BibleVersions::normalize($request->query('version'));

        return ApiResponse::success($request, [
            'version' => BibleTextStore::version($version),
            'versions' => BibleVersions::catalog(),
            'books' => array_map(fn (array $book): array => [
                'id' => $book['id'],
                'slug' => $book['slug'],
                'name' => $book['name'],
                'abbrev' => strtoupper($book['id']),
                'testament' => $book['testament'],
                'chapters' => $book['chapters'],
            ], BibleCanon::books()),
        ]);
    }

    public function chapter(Request $request, string $book, int $chapter): JsonResponse
    {
        try {
            return ApiResponse::success(
                $request,
                BibleTextStore::chapter($book, $chapter, $request->query('version')),
            );
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'not installed')) {
                throw new UnprocessableEntityHttpException($exception->getMessage());
            }
            throw new NotFoundHttpException('That Bible chapter was not found.');
        }
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $version = $request->query('version');

        try {
            return ApiResponse::success($request, [
                'query' => $query,
                'version' => BibleTextStore::version($version),
                'results' => $query === '' ? [] : BibleTextStore::search($query, $limit, $version),
            ]);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'not installed')) {
                throw new UnprocessableEntityHttpException($exception->getMessage());
            }
            throw $exception;
        }
    }

    public function plans(Request $request): JsonResponse
    {
        return ApiResponse::success($request, BibleReadingPlanGenerator::summaries());
    }

    public function plan(Request $request, string $plan): JsonResponse
    {
        $payload = BibleReadingPlanGenerator::plan($plan);
        if ($payload === null) {
            throw new NotFoundHttpException('That Bible reading plan was not found.');
        }

        return ApiResponse::success($request, $payload);
    }
}
