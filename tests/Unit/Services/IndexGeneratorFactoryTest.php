<?php

namespace Tests\Unit\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Writers\FileWriter;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGenerator as IndexGeneratorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use DevSite\LaravelMigrationSearcher\Services\IndexGeneratorFactory;
use PHPUnit\Framework\TestCase;

class IndexGeneratorFactoryTest extends TestCase
{
    public function testCreateReturnsIndexGeneratorInstance(): void
    {
        $dataBuilder = $this->createStub(IndexDataBuilderContract::class);
        $fileWriter = $this->createStub(FileWriter::class);
        $renderer = $this->createStub(Renderer::class);

        $factory = new IndexGeneratorFactory($dataBuilder, $fileWriter);
        $generator = $factory->create('/some/path', $renderer);

        $this->assertInstanceOf(IndexGeneratorContract::class, $generator);
        $this->assertInstanceOf(IndexGenerator::class, $generator);
    }

    public function testCreateReturnsNewInstanceEachTime(): void
    {
        $dataBuilder = $this->createStub(IndexDataBuilderContract::class);
        $fileWriter = $this->createStub(FileWriter::class);
        $renderer = $this->createStub(Renderer::class);

        $factory = new IndexGeneratorFactory($dataBuilder, $fileWriter);

        $generator1 = $factory->create('/path/one', $renderer);
        $generator2 = $factory->create('/path/two', $renderer);

        $this->assertNotSame($generator1, $generator2);
    }
}
