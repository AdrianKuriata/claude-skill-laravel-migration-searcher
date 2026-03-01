<?php

namespace DevSite\LaravelMigrationSearcher\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DependencyParser as DependencyParserContract;

class DependencyParser implements DependencyParserContract
{
    public function parse(string $content): array
    {
        return $this->extractDependencies($content);
    }

    public function extractDependencies(string $content): array
    {
        $requires = [];
        $dependsOn = [];
        $foreignKeys = [];

        if (preg_match_all('/@requires?\s+([^\s\n]+)/', $content, $matches)) {
            foreach ($matches[1] as $dep) {
                $requires[] = $dep;
            }
        }

        if (preg_match_all('/@depends?\s+on\s+([^\s\n]+)/', $content, $matches)) {
            foreach ($matches[1] as $dep) {
                $dependsOn[] = $dep;
            }
        }

        if (preg_match_all(
            '/->foreign\s*\([\'"]([^"\']+)[\'"]\)\s*->references\s*\([\'"]([^"\']+)[\'"]\)\s*->on\s*\([\'"]([^"\']+)[\'"]/',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $foreignKeys[] = [
                    'column' => $match[1],
                    'references' => $match[2],
                    'on_table' => $match[3],
                ];
            }
        }

        $result = [];

        if (!empty($requires)) {
            $result['requires'] = $requires;
        }

        if (!empty($dependsOn)) {
            $result['depends_on'] = $dependsOn;
        }

        if (!empty($foreignKeys)) {
            $result['foreign_keys'] = $foreignKeys;
        }

        return $result;
    }
}
