<?php

namespace App\Support\Administration;

use App\Administration\AdminWorkItemPriority;
use App\Administration\AdminWorkItemStatus;
use App\Models\AdminWorkItem;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\ScopeReference;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ManageAdminWorkItemAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{title: string, body?: string|null, priority?: string, due_at?: string|null, assigned_to_user_id?: string|null}  $attributes
     */
    public function create(User $actor, ScopeReference $scope, array $attributes): AdminWorkItem
    {
        $title = Str::squish($attributes['title']);
        if ($title === '' || Str::length($title) > 191) {
            throw new InvalidArgumentException('Task titles must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use ($actor, $scope, $attributes, $title): AdminWorkItem {
            $item = AdminWorkItem::query()->create([
                'title' => $title,
                'body' => $this->nullable($attributes['body'] ?? null),
                'status' => AdminWorkItemStatus::Open,
                'priority' => AdminWorkItemPriority::from($attributes['priority'] ?? AdminWorkItemPriority::Normal->value),
                'due_at' => isset($attributes['due_at']) && $attributes['due_at'] !== null && $attributes['due_at'] !== ''
                    ? CarbonImmutable::parse((string) $attributes['due_at'])
                    : null,
                'assigned_to_user_id' => $attributes['assigned_to_user_id'] ?? null,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $this->audit('administration.work_item.created', $actor, $item, $scope);

            return $item;
        }, attempts: 3);
    }

    /**
     * @param  array{title?: string, body?: string|null, priority?: string, due_at?: string|null, assigned_to_user_id?: int|null, status?: string}  $attributes
     */
    public function update(User $actor, ScopeReference $scope, AdminWorkItem $item, array $attributes): AdminWorkItem
    {
        return DB::transaction(function () use ($actor, $scope, $item, $attributes): AdminWorkItem {
            $locked = AdminWorkItem::query()->lockForUpdate()->findOrFail($item->getKey());
            if ($locked->status === AdminWorkItemStatus::Archived) {
                throw new InvalidArgumentException('Archived tasks cannot be edited.');
            }
            if (isset($attributes['title'])) {
                $title = Str::squish((string) $attributes['title']);
                if ($title === '' || Str::length($title) > 191) {
                    throw new InvalidArgumentException('Task titles must contain between 1 and 191 characters.');
                }
                $locked->title = $title;
            }
            if (array_key_exists('body', $attributes)) {
                $locked->body = $this->nullable($attributes['body']);
            }
            if (isset($attributes['priority'])) {
                $locked->priority = AdminWorkItemPriority::from((string) $attributes['priority']);
            }
            if (array_key_exists('due_at', $attributes)) {
                $locked->due_at = $attributes['due_at'] ? CarbonImmutable::parse((string) $attributes['due_at']) : null;
            }
            if (array_key_exists('assigned_to_user_id', $attributes)) {
                $locked->assigned_to_user_id = $attributes['assigned_to_user_id'];
            }
            if (isset($attributes['status'])) {
                $status = AdminWorkItemStatus::from((string) $attributes['status']);
                if ($status === AdminWorkItemStatus::Archived) {
                    throw new InvalidArgumentException('Use archive to close a task for history.');
                }
                $locked->status = $status;
                $locked->closed_at = $status === AdminWorkItemStatus::Completed ? now()->utc() : null;
            }
            $locked->save();
            $this->audit('administration.work_item.updated', $actor, $locked, $scope);

            return $locked;
        }, attempts: 3);
    }

    public function archive(User $actor, ScopeReference $scope, AdminWorkItem $item): AdminWorkItem
    {
        return DB::transaction(function () use ($actor, $scope, $item): AdminWorkItem {
            $locked = AdminWorkItem::query()->lockForUpdate()->findOrFail($item->getKey());
            $locked->status = AdminWorkItemStatus::Archived;
            $locked->closed_at = now()->utc();
            $locked->save();
            $this->audit('administration.work_item.archived', $actor, $locked, $scope);

            return $locked;
        }, attempts: 3);
    }

    private function audit(string $action, User $actor, AdminWorkItem $item, ScopeReference $scope): void
    {
        $this->recordAuditEvent->handle(new AuditEventData(
            action: $action,
            actor: $actor,
            targetType: 'admin_work_item',
            targetId: $item->public_id,
            scopeType: $scope->type,
            scopeId: $scope->key,
        ));
    }

    private function nullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
