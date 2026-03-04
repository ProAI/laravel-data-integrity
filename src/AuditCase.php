<?php

namespace ProAI\DataIntegrity;

use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

abstract class AuditCase
{
    /**
     * The fully-qualified Eloquent model class this audit operates on.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model;

    /**
     * Define an inline audit.
     *
     * @return \ProAI\DataIntegrity\Audit
     */
    protected function audit(): Audit
    {
        return new Audit($this->model);
    }

    /**
     * Delegate to a registered check class (by class-string or alias).
     *
     * @param  class-string<IntegrityCheck>|string  $checkClassOrAlias
     * @return \ProAI\DataIntegrity\Audit
     */
    protected function auditUsing(string $checkClassOrAlias, array $args = []): Audit
    {
        $class = AuditManager::resolveCheck($checkClassOrAlias);
        $check = new $class(...$args);

        $description = method_exists($check, 'description')
            ? $check->description()
            : Str::of(class_basename($class))->snake()->replace('_', ' ')->toString();

        $audit = $this->audit()
            ->description($description)
            ->validate($check->validate(...));

        if (method_exists($check, 'query')) {
            $audit->query($check->query(...));
        }

        if (isset($check->chunkSize)) {
            $audit->chunkSize($check->chunkSize);
        }

        if (method_exists($check, 'before')) {
            $audit->before($check->before(...));
        }

        if (method_exists($check, 'after')) {
            $audit->after($check->after(...));
        }

        return $audit;
    }

    /**
     * Get the model class this audit operates on.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Get all audits by invoking each check* method.
     *
     * @return Audit[]
     */
    public function getAudits()
    {
        $audits = [];

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! str_starts_with($method->getName(), 'check')) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $audit = $this->{$method->getName()}();

            if ($audit->getDescription() === null) {
                $audit->description(
                    Str::of($method->getName())
                        ->after('check')
                        ->snake()
                        ->replace('_', ' ')
                        ->trim()
                        ->toString()
                );
            }

            $audits[] = $audit;
        }

        return $audits;
    }
}
