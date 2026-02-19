<?php

namespace Tests\Unit;

use DevSite\LaravelMigrationSearcher\Services\ComplexityCalculator;
use PHPUnit\Framework\TestCase;

class ComplexityCalculatorTest extends TestCase
{
    protected ComplexityCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ComplexityCalculator();
    }

    public function testMinimumComplexityIsOne(): void
    {
        $result = [
            'tables' => [],
            'ddl_operations' => [],
            'dml_operations' => [],
            'raw_sql' => [],
            'foreign_keys' => [],
        ];

        $this->assertSame(1, $this->calculator->calculate($result));
    }

    public function testTablesAddToComplexity(): void
    {
        $result = [
            'tables' => ['users' => [], 'posts' => []],
            'ddl_operations' => [],
            'dml_operations' => [],
            'raw_sql' => [],
            'foreign_keys' => [],
        ];

        $this->assertSame(2, $this->calculator->calculate($result));
    }

    public function testDdlOperationsAddHalfPointEach(): void
    {
        $result = [
            'tables' => ['users' => []],
            'ddl_operations' => [['method' => 'string'], ['method' => 'integer']],
            'dml_operations' => [],
            'raw_sql' => [],
            'foreign_keys' => [],
        ];

        // 1 table + 2 * 0.5 = 2
        $this->assertSame(2, $this->calculator->calculate($result));
    }

    public function testDmlOperationsAddTwoPointsEach(): void
    {
        $result = [
            'tables' => [],
            'ddl_operations' => [],
            'dml_operations' => [['type' => 'UPDATE'], ['type' => 'INSERT']],
            'raw_sql' => [],
            'foreign_keys' => [],
        ];

        // 2 * 2 = 4
        $this->assertSame(4, $this->calculator->calculate($result));
    }

    public function testRawSqlAddsThreePointsEach(): void
    {
        $result = [
            'tables' => [],
            'ddl_operations' => [],
            'dml_operations' => [],
            'raw_sql' => [['type' => 'statement'], ['type' => 'unprepared']],
            'foreign_keys' => [],
        ];

        // 2 * 3 = 6
        $this->assertSame(6, $this->calculator->calculate($result));
    }

    public function testForeignKeysAddOneAndHalfPointsEach(): void
    {
        $result = [
            'tables' => [],
            'ddl_operations' => [],
            'dml_operations' => [],
            'raw_sql' => [],
            'foreign_keys' => [['column' => 'a'], ['column' => 'b']],
        ];

        // 2 * 1.5 = 3
        $this->assertSame(3, $this->calculator->calculate($result));
    }

    public function testMaximumComplexityIsTen(): void
    {
        $result = [
            'tables' => array_fill(0, 5, []),
            'ddl_operations' => array_fill(0, 10, []),
            'dml_operations' => array_fill(0, 5, []),
            'raw_sql' => array_fill(0, 5, []),
            'foreign_keys' => array_fill(0, 5, []),
        ];

        $this->assertSame(10, $this->calculator->calculate($result));
    }

    public function testHandlesMissingKeys(): void
    {
        $this->assertSame(1, $this->calculator->calculate([]));
    }

    public function testIsPureFunction(): void
    {
        $result = [
            'tables' => ['users' => []],
            'ddl_operations' => [],
            'dml_operations' => [],
            'raw_sql' => [],
            'foreign_keys' => [],
        ];

        $first = $this->calculator->calculate($result);
        $second = $this->calculator->calculate($result);

        $this->assertSame($first, $second);
    }
}
