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
        $this->assertSame(1, $this->calculator->calculate([], [], [], [], []));
    }

    public function testTablesAddToComplexity(): void
    {
        $tables = ['users' => [], 'posts' => []];

        $this->assertSame(2, $this->calculator->calculate($tables, [], [], [], []));
    }

    public function testDdlOperationsAddHalfPointEach(): void
    {
        $tables = ['users' => []];
        $ddlOperations = [['method' => 'string'], ['method' => 'integer']];

        $this->assertSame(2, $this->calculator->calculate($tables, $ddlOperations, [], [], []));
    }

    public function testDmlOperationsAddTwoPointsEach(): void
    {
        $dmlOperations = [['type' => 'UPDATE'], ['type' => 'INSERT']];

        $this->assertSame(4, $this->calculator->calculate([], [], $dmlOperations, [], []));
    }

    public function testRawSqlAddsThreePointsEach(): void
    {
        $rawSql = [['type' => 'statement'], ['type' => 'unprepared']];

        $this->assertSame(6, $this->calculator->calculate([], [], [], $rawSql, []));
    }

    public function testForeignKeysAddOneAndHalfPointsEach(): void
    {
        $foreignKeys = [['column' => 'a'], ['column' => 'b']];

        $this->assertSame(3, $this->calculator->calculate([], [], [], [], $foreignKeys));
    }

    public function testMaximumComplexityIsTen(): void
    {
        $tables = array_fill(0, 5, []);
        $ddlOperations = array_fill(0, 10, []);
        $dmlOperations = array_fill(0, 5, []);
        $rawSql = array_fill(0, 5, []);
        $foreignKeys = array_fill(0, 5, []);

        $this->assertSame(10, $this->calculator->calculate(
            $tables,
            $ddlOperations,
            $dmlOperations,
            $rawSql,
            $foreignKeys,
        ));
    }

    public function testIsPureFunction(): void
    {
        $tables = ['users' => []];

        $first = $this->calculator->calculate($tables, [], [], [], []);
        $second = $this->calculator->calculate($tables, [], [], [], []);

        $this->assertSame($first, $second);
    }
}
