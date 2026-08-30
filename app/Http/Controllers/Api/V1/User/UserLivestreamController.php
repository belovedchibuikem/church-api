<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Livestream\LivestreamStatus;
use App\Models\Livestream;
use App\Models\LivestreamComment;
use App\Models\Person;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserLivestreamController extends Controller
{
    public function comments(Request $request, string $livestream): JsonResponse
    {
        $stream = Livestream::query()->where('public_id', $livestream)->firstOrFail();
        $since = $request->query('since');

        $query = LivestreamComment::query()
            ->with(['person.profile'])
            ->where('livestream_id', $stream->getKey())
            ->orderBy('created_at')
            ->orderBy('id');

        if (is_string($since) && $since !== '') {
            $query->where('created_at', '>', $since);
        }

        $comments = $query->limit(100)->get()->map(fn (LivestreamComment $comment): array => [
            'id' => $comment->public_id,
            'body' => $comment->body,
            'person_id' => $comment->person?->public_id,
            'person_name' => PersonDisplayName::of($comment->person) ?: 'Member',
            'created_at' => $comment->created_at?->utc()->toIso8601String(),
        ])->all();

        return ApiResponse::success($request, $comments);
    }

    public function storeComment(Request $request, string $livestream): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->person instanceof Person) {
            abort(401);
        }

        $stream = Livestream::query()->where('public_id', $livestream)->firstOrFail();
        if ($stream->status !== LivestreamStatus::Live) {
            throw ValidationException::withMessages([
                'body' => ['Chat is only open while the service is live.'],
            ]);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $comment = new LivestreamComment;
        $comment->forceFill([
            'livestream_id' => $stream->getKey(),
            'person_id' => $user->person->getKey(),
            'body' => trim($validated['body']),
        ]);
        $comment->save();
        $comment->load(['person.profile']);

        return ApiResponse::success($request, [
            'id' => $comment->public_id,
            'body' => $comment->body,
            'person_id' => $comment->person?->public_id,
            'person_name' => PersonDisplayName::of($comment->person) ?: 'Member',
            'created_at' => $comment->created_at?->utc()->toIso8601String(),
        ], status: 201);
    }

    public function react(Request $request, string $livestream): JsonResponse
    {
        $stream = Livestream::query()->where('public_id', $livestream)->firstOrFail();
        $stream->increment('reaction_count');
        $stream->refresh();

        return ApiResponse::success($request, [
            'id' => $stream->public_id,
            'reaction_count' => $stream->reaction_count,
        ]);
    }
}
