<?php
declare(strict_types=1);

namespace TechNest\ProductSpecs\Model;

/**
 * Turns flat attribute rows into groups sorted like the attribute set, without empty values or empty groups
 */
class SpecSheet
{
    /**
     * Group the rows
     *
     * @param mixed[][] $rows Each with group_id, group, group_sort, sort, code, label and value
     * @return array<int, array<string, mixed>> Each with label and rows (code, label, value)
     */
    public function build(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $value = trim((string)$row['value']);
            if ($value === '') {
                continue;
            }
            $id = (int)$row['group_id'];
            $groups[$id] ??= ['label' => (string)$row['group'], 'sort' => (int)$row['group_sort'], 'rows' => []];
            $groups[$id]['rows'][] = [
                'code' => (string)$row['code'],
                'label' => (string)$row['label'],
                'value' => $value,
                'sort' => (int)$row['sort'],
            ];
        }

        uasort($groups, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);
        $sheet = [];
        foreach ($groups as $group) {
            usort($group['rows'], static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);
            $sheet[] = [
                'label' => $group['label'],
                'rows' => array_map(
                    static fn (array $row): array => [
                        'code' => $row['code'],
                        'label' => $row['label'],
                        'value' => $row['value'],
                    ],
                    $group['rows']
                ),
            ];
        }
        return $sheet;
    }
}
