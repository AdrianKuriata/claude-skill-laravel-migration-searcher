<?php

namespace Tests\Unit\Services;

use DevSite\LaravelMigrationSearcher\Services\ComplexityCalculator;
use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;
use PHPUnit\Framework\TestCase;

class ComplexityCalculatorTest extends TestCase
{
    protected ComplexityCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ComplexityCalculator();
    }

    public function testReturnsComplexityScore(): void
    {
        $result = $this->calculator->calculate([], [], [], [], []);

        $this->assertInstanceOf(ComplexityScore::class, $result);
    }

    public function testMinimumComplexityIsOne(): void
    {
        $this->assertSame(1, $this->calculator->calculate([], [], [], [], [])->value);
    }

    public function testTablesAddToComplexity(): void
    {
        $tables = ['users' => [], 'posts' => []];

        $this->assertSame(2, $this->calculator->calculate($tables, [], [], [], [])->value);
    }

    public function testDdlOperationsAddHalfPointEach(): void
    {
        $tables = ['users' => []];
        $ddlOperations = [['method' => 'string'], ['method' => 'integer']];

        $this->assertSame(2, $this->calculator->calculate($tables, $ddlOperations, [], [], [])->value);
    }

    public function testDmlOperationsAddTwoPointsEach(): void
    {
        $dmlOperations = [['type' => 'UPDATE'], ['type' => 'INSERT']];

        $this->assertSame(4, $this->calculator->calculate([], [], $dmlOperations, [], [])->value);
    }

    public function testRawSqlAddsThreePointsEach(): void
    {
        $rawSql = [['type' => 'statement'], ['type' => 'unprepared']];

        $this->assertSame(6, $this->calculator->calculate([], [], [], $rawSql, [])->value);
    }

    public function testForeignKeysAddOneAndHalfPointsEach(): void
    {
        $foreignKeys = [['column' => 'a'], ['column' => 'b']];

        $this->assertSame(3, $this->calculator->calculate([], [], [], [], $foreignKeys)->value);
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
        )->value);
    }

    public function testIsPureFunction(): void
    {
        $tables = ['users' => []];

        $first = $this->calculator->calculate($tables, [], [], [], [])->value;
        $second = $this->calculator->calculate($tables, [], [], [], [])->value;

        $this->assertSame($first, $second);
    }
}
