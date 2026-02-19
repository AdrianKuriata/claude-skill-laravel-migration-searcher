<?php

namespace DevSite\LaravelMigrationSearcher\Services\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\ContentParserInterface;

class DependencyParser implements ContentParserInterface
{
    public function parse(string $content): array
    {
        return $this->extractDependencies($content);
    }

    public function extractDependencies(string $content): array
    {
        $dependencies = [];

        if (preg_match_all('/@requires?\s+([^\s\n]+)/', $content, $matches)) {
            foreach ($matches[1] as $dep) {
                $dependencies['requires'][] = $dep;
            }
        }

        if (preg_match_all('/@depends?\s+on\s+([^\s\n]+)/', $content, $matches)) {
            foreach ($matches[1] as $dep) {
                $dependencies['depends_on'][] = $dep;
            }
        }

        if (preg_match_all(
            '/->foreign\s*\([\'"]([^"\']+)[\'"]\)\s*->references\s*\([\'"]([^"\']+)[\'"]\)\s*->on\s*\([\'"]([^"\']+)[\'"]/',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $dependencies['foreign_keys'][] = [
                    'column' => $match[1],
                    'references' => $match[2],
                    'on_table' => $match[3],
                ];
            }
        }

        return $dependencies;
    }
}
