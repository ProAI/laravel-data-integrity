<?php

namespace ProAI\DataIntegrity;

use Illuminate\Support\Collection;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class AuditDiscovery
{
    /**
     * Create a new AuditDiscovery instance.
     */
    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * Discover all concrete Audit subclasses under the base path.
     *
     * @return Collection<int, class-string<AuditCase>>
     */
    public function discover(?string $subdirectory = null): Collection
    {
        $path = $subdirectory
            ? rtrim($this->basePath, '/').'/'.$subdirectory
            : $this->basePath;

        if (! is_dir($path)) {
            return collect();
        }

        return collect(Finder::create()->files()->name('*.php')->in($path))
            ->map(fn (SplFileInfo $file) => $this->classFromFile($file))
            ->filter(fn (?string $class) => $class && $this->isAudit($class))
            ->values();
    }

    /**
     * Extract the fully-qualified class name from a PHP file.
     *
     * @return string|null
     */
    protected function classFromFile(SplFileInfo $file): ?string
    {
        $contents = file_get_contents($file->getRealPath());

        $namespace = null;
        $class = null;

        if (preg_match('/^namespace\s+(.+?);/m', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            $class = $matches[1];
        }

        if (! $namespace || ! $class) {
            return null;
        }

        return $namespace.'\\'.$class;
    }

    /**
     * Determine whether the given class is a concrete Audit subclass.
     *
     * @return bool
     */
    protected function isAudit(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return $reflection->isSubclassOf(AuditCase::class) && ! $reflection->isAbstract();
    }
}
