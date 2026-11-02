<?php
declare(strict_types=1);

namespace TechNest\ProductSpecs\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use TechNest\ProductSpecs\Model\SpecSheet;

class SpecSheetTest extends TestCase
{
    /**
     * Row as the view model passes it
     *
     * @param int $groupId
     * @param int $groupSort
     * @param int $sort
     * @param string $code
     * @param string $value
     * @return array<string, mixed>
     */
    private function row(int $groupId, int $groupSort, int $sort, string $code, string $value): array
    {
        return [
            'group_id' => $groupId,
            'group' => 'Group ' . $groupId,
            'group_sort' => $groupSort,
            'sort' => $sort,
            'code' => $code,
            'label' => ucfirst($code),
            'value' => $value,
        ];
    }

    public function testGroupsAndRowsFollowTheAttributeSetOrder(): void
    {
        $sheet = (new SpecSheet())->build([
            $this->row(7, 10, 1, 'warranty_months', '12'),
            $this->row(9, 15, 3, 'tn_gpu', 'RTX 4070'),
            $this->row(9, 15, 1, 'brand', 'Lenovo'),
            $this->row(9, 15, 2, 'tn_cpu', 'Intel Core i7'),
        ]);

        $this->assertSame(['Group 7', 'Group 9'], array_column($sheet, 'label'));
        $this->assertSame(['brand', 'tn_cpu', 'tn_gpu'], array_column($sheet[1]['rows'], 'code'));
        $this->assertSame(
            ['code' => 'brand', 'label' => 'Brand', 'value' => 'Lenovo'],
            $sheet[1]['rows'][0]
        );
    }

    public function testDropsEmptyValuesAndEmptyGroups(): void
    {
        $sheet = (new SpecSheet())->build([
            $this->row(1, 10, 1, 'color', '  '),
            $this->row(2, 20, 1, 'brand', ' Apple '),
            $this->row(2, 20, 2, 'tn_camera', ''),
        ]);

        $this->assertCount(1, $sheet);
        $this->assertSame([['code' => 'brand', 'label' => 'Brand', 'value' => 'Apple']], $sheet[0]['rows']);
    }

    public function testNoRows(): void
    {
        $this->assertSame([], (new SpecSheet())->build([]));
    }
}
