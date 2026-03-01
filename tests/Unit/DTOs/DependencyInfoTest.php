<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\DependencyInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DependencyInfoTest extends TestCase
{
    #[Test]
    public function itStoresDependencyData(): void
    {
        $dep = new DependencyInfo(
            requires: ['users_table'],
            dependsOn: ['roles_table'],
            foreignKeys: [['column' => 'user_id', 'references' => 'id', 'on_table' => 'users']],
        );

        $this->assertSame(['users_table'], $dep->requires);
        $this->assertSame(['roles_table'], $dep->dependsOn);
        $this->assertCount(1, $dep->foreignKeys);
    }

    #[Test]
    public function itDefaultsToEmptyArrays(): void
    {
        $dep = new DependencyInfo();

        $this->assertEmpty($dep->requires);
        $this->assertEmpty($dep->dependsOn);
        $this->assertEmpty($dep->foreignKeys);
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $dep = new DependencyInfo(
            requires: ['a'],
            dependsOn: ['b'],
            foreignKeys: [],
        );

        $result = $dep->toArray();

        $this->assertSame(['a'], $result['requires']);
        $this->assertSame(['b'], $result['depends_on']);
        $this->assertSame([], $result['foreign_keys']);
    }
}
