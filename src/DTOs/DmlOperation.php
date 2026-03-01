<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

use DevSite\LaravelMigrationSearcher\Enums\DmlOperationType;

final readonly class DmlOperation extends BaseDTO
{
    /**
     * @param string[] $whereConditions
     * @param string[] $columnsUpdated
     * @param string[] $dbRawExpressions
     * @param string[] $operationsInLoop
     */
    public function __construct(
        public DmlOperationType $type,
        public ?string $table = null,
        public ?string $model = null,
        public ?string $variable = null,
        public ?string $relation = null,
        public ?string $method = null,
        public ?string $note = null,
        public ?string $dataPreview = null,
        public array $whereConditions = [],
        public array $columnsUpdated = [],
        public bool $hasDbRaw = false,
        public array $dbRawExpressions = [],
        public array $operationsInLoop = [],
    ) {
    }
}
