<?php

namespace App\Support\Livestream;

use App\Livestream\LivestreamStatus;
use App\Models\Church;
use App\Models\Livestream;
use InvalidArgumentException;

final class UpsertLivestreamAction
{
    /**
     * @param  array{title: string, subtitle?: string|null, host_name?: string|null, youtube_url: string, status?: string, starts_at?: string|null, church_id?: string|null, viewer_count?: int, reaction_count?: int}  $attributes
     */
    public function handle(array $attributes): Livestream
    {
        $videoId = YoutubeLivestreamUrl::parseVideoId($attributes['youtube_url']);
        $status = LivestreamStatus::tryFrom((string) ($attributes['status'] ?? LivestreamStatus::Live->value))
            ?? LivestreamStatus::Live;

        $churchId = null;
        if (! empty($attributes['church_id'])) {
            $churchId = Church::query()->where('public_id', $attributes['church_id'])->value('id');
        }

        $stream = Livestream::query()
            ->where('status', LivestreamStatus::Live->value)
            ->latest('id')
            ->first() ?? new Livestream;

        if ($status === LivestreamStatus::Live) {
            Livestream::query()
                ->where('status', LivestreamStatus::Live->value)
                ->when($stream->exists, fn ($q) => $q->whereKeyNot($stream->getKey()))
                ->update([
                    'status' => LivestreamStatus::Ended->value,
                    'ended_at' => now()->utc(),
                ]);
        }

        $stream->fill([
            'church_id' => $churchId,
            'title' => $attributes['title'],
            'subtitle' => $attributes['subtitle'] ?? null,
            'host_name' => $attributes['host_name'] ?? null,
            'provider' => 'youtube',
            'external_id' => $videoId,
            'watch_url' => YoutubeLivestreamUrl::watchUrl($videoId),
            'embed_url' => YoutubeLivestreamUrl::embedUrl($videoId),
            'status' => $status,
            'viewer_count' => (int) ($attributes['viewer_count'] ?? $stream->viewer_count ?? 0),
            'reaction_count' => (int) ($attributes['reaction_count'] ?? $stream->reaction_count ?? 0),
            'starts_at' => isset($attributes['starts_at']) && $attributes['starts_at'] !== ''
                ? $attributes['starts_at']
                : ($stream->starts_at ?? now()->utc()),
            'ended_at' => $status === LivestreamStatus::Ended ? now()->utc() : null,
        ]);
        $stream->save();

        return $stream->fresh(['church:id,public_id,name']) ?? $stream;
    }
}
