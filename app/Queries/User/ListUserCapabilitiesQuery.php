<?php

namespace App\Queries\User;

use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ListUserCapabilitiesQuery
{
    /**
     * @return array{
     *     permissions: list<string>,
     *     scopes: list<array{type: string, key: string}>,
     *     roles: list<array{code: string, name: string, scopes: list<array{type: string, key: string}>}>
     * }
     */
    public function handle(User $user, ?Carbon $at = null): array
    {
        $at ??= now()->utc();

        $assignments = RoleAssignment::query()
            ->with([
                'role.rolePermissions.permission:id,code',
                'scopeAssignments:id,role_assignment_id,scope_type,scope_key',
            ])
            ->whereBelongsTo($user)
            ->active($at)
            ->get();

        /** @var Collection<int, string> $permissions */
        $permissions = $assignments
            ->flatMap(fn (RoleAssignment $assignment) => $assignment->role->rolePermissions)
            ->map(fn ($rolePermission) => $rolePermission->permission?->code)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        /** @var Collection<int, array{type: string, key: string}> $scopes */
        $scopes = $assignments
            ->flatMap(fn (RoleAssignment $assignment) => $assignment->scopeAssignments)
            ->map(fn ($scope) => [
                'type' => $scope->scope_type,
                'key' => $scope->scope_key,
            ])
            ->unique(fn (array $scope): string => $scope['type'].':'.$scope['key'])
            ->values();

        $roles = $assignments
            ->filter(fn (RoleAssignment $assignment) => $assignment->role !== null)
            ->map(fn (RoleAssignment $assignment) => [
                'code' => (string) $assignment->role->code,
                'name' => (string) $assignment->role->name,
                'scopes' => $assignment->scopeAssignments
                    ->map(fn ($scope) => [
                        'type' => $scope->scope_type,
                        'key' => $scope->scope_key,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return [
            'permissions' => $permissions->all(),
            'scopes' => $scopes->all(),
            'roles' => $roles,
        ];
    }
}
