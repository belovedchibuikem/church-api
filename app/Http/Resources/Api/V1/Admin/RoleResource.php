<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\RolePermission;
use App\Support\Authorization\AuthorizationBundleCatalog;
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
            'assignment_count' => $this->whenCounted('assignments', fn (): int => (int) $this->assignments_count),
            'is_system' => $this->isSystemRole(),
            'permissions' => $this->whenLoaded(
                'rolePermissions',
                fn (): array => $this->rolePermissions
                    ->map(fn (RolePermission $grant): array => (new PermissionResource($grant->permission))->resolve($request))
                    ->values()
                    ->all(),
            ),
        ];
    }

    private function isSystemRole(): bool
    {
        $code = (string) $this->code;

        return $code === AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE
            || array_key_exists($code, AuthorizationBundleCatalog::BUNDLES);
    }
}
