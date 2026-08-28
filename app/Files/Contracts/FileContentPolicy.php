<?php

namespace App\Files\Contracts;

use App\Files\Data\InspectedFile;
use Illuminate\Http\UploadedFile;

interface FileContentPolicy
{
    public function inspect(UploadedFile $file): InspectedFile;
}
