<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'permissions' => $this->whenLoaded(
                'rolePermissions',
                fn (): array => $this->rolePermissions
                    ->map(fn (RolePermission $grant): array => (new PermissionResource($grant->permission))->resolve($request))
                    ->values()
                    ->all(),
            ),
        ];
    }
}
