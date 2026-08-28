<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Files\FileAssetClassification;
use App\Files\FileAssetStreamResponse;
use App\Http\Controllers\Controller;
use App\Models\FileAsset;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMediaController extends Controller
{
    public function show(Request $request, string $file, FileAssetStreamResponse $streams): StreamedResponse
    {
        $target = FileAsset::query()
            ->available()
            ->where('public_id', $file)
            ->where('classification', FileAssetClassification::Public->value)
            ->where('detected_mime_type', 'like', 'image/%')
            ->firstOrFail();

        return $streams->handle($target, $request->boolean('download', false));
    }
}
