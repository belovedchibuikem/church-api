<?php

namespace App\Support\Kca;

use App\Models\KcaAssignment;
use App\Models\KcaSoulWin;
use InvalidArgumentException;

class KcaSoulTreeService
{
    /**
     * @return list<int>
     */
    public function levels(KcaAssignment $assignment): array
    {
        if (! $assignment->isSoulWinning()) {
            return [];
        }
        $spec = $assignment->soul_tree_spec ?? [];
        $levels = $spec['levels'] ?? [];
        if (! is_array($levels)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $levels), fn (int $n): bool => $n > 0));
    }

    public function isComplete(KcaAssignment $assignment): bool
    {
        $levels = $this->levels($assignment);
        if ($levels === []) {
            return true;
        }

        $nodes = KcaSoulWin::query()
            ->where('kca_assignment_id', $assignment->getKey())
            ->get(['id', 'parent_id', 'depth']);

        return $this->subtreeFilled(null, 1, $levels, $nodes);
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(KcaAssignment $assignment): array
    {
        $levels = $this->levels($assignment);
        $nodes = KcaSoulWin::query()
            ->where('kca_assignment_id', $assignment->getKey())
            ->orderBy('depth')
            ->orderBy('id')
            ->get();
        $required = $this->requiredCount($levels);
        $complete = $this->isComplete($assignment);

        return [
            'kind' => $assignment->assignment_kind ?? 'standard',
            'levels' => $levels,
            'required_souls' => $required,
            'recorded_souls' => $nodes->count(),
            'complete' => $complete,
            'open' => $assignment->isSoulWinning() && ! $complete,
            'tree' => $this->treePayload($nodes, null),
        ];
    }

    public function assertCanAdd(KcaAssignment $assignment, ?KcaSoulWin $parent): int
    {
        $levels = $this->levels($assignment);
        if ($levels === []) {
            throw new InvalidArgumentException('This assignment is not a soul-winning tree.');
        }
        $depth = $parent === null ? 1 : ((int) $parent->depth) + 1;
        if ($depth < 1 || $depth > count($levels)) {
            throw new InvalidArgumentException('This soul would sit outside the defined winning tree.');
        }
        if ($parent !== null && (int) $parent->kca_assignment_id !== (int) $assignment->getKey()) {
            throw new InvalidArgumentException('The parent soul does not belong to this assignment.');
        }
        $required = $levels[$depth - 1];
        $existing = KcaSoulWin::query()
            ->where('kca_assignment_id', $assignment->getKey())
            ->where('parent_id', $parent?->getKey())
            ->count();
        if ($existing >= $required) {
            throw new InvalidArgumentException('This branch already has the required number of souls.');
        }

        return $depth;
    }

    public function assertCompleteForClosure(KcaAssignment $assignment): void
    {
        if (! $assignment->isSoulWinning()) {
            return;
        }
        if (! $this->isComplete($assignment)) {
            throw new InvalidArgumentException('Soul-winning assignments stay open until the defined tree is complete.');
        }
    }

    /**
     * @param  list<int>  $levels
     */
    public function requiredCount(array $levels): int
    {
        $total = 0;
        $branch = 1;
        foreach ($levels as $width) {
            $branch *= $width;
            $total += $branch;
        }

        return $total;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, KcaSoulWin>  $nodes
     * @param  list<int>  $levels
     */
    private function subtreeFilled(?int $parentId, int $depth, array $levels, $nodes): bool
    {
        if ($depth > count($levels)) {
            return true;
        }
        $children = $parentId === null
            ? $nodes->filter(fn (KcaSoulWin $row): bool => $row->parent_id === null)
            : $nodes->filter(fn (KcaSoulWin $row): bool => (int) $row->parent_id === $parentId);
        if ($children->count() < $levels[$depth - 1]) {
            return false;
        }
        foreach ($children as $child) {
            if (! $this->subtreeFilled((int) $child->id, $depth + 1, $levels, $nodes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, KcaSoulWin>  $nodes
     * @return list<array<string, mixed>>
     */
    private function treePayload($nodes, ?int $parentId): array
    {
        $children = $parentId === null
            ? $nodes->filter(fn (KcaSoulWin $row): bool => $row->parent_id === null)
            : $nodes->filter(fn (KcaSoulWin $row): bool => (int) $row->parent_id === $parentId);

        return $children->map(fn (KcaSoulWin $row): array => [
            'id' => $row->public_id,
            'depth' => $row->depth,
            'given_name' => $row->given_name,
            'family_name' => $row->family_name,
            'phone' => $row->phone,
            'email' => $row->email,
            'notes' => $row->notes,
            'won_at' => $row->won_at?->utc()->toIso8601String(),
            'children' => $this->treePayload($nodes, (int) $row->id),
        ])->values()->all();
    }
}
