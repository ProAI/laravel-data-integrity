<?php

namespace ProAI\DataIntegrity;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(name: 'db:audit', description: 'Run database audits.')]
class AuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:audit
                {directory? : Only run audits from this subdirectory}
                {--model= : Only run audits for this model}
                {--filter= : Only run audits whose description matches this string}
                {--fix : Fix issues automatically}
                {--max-violations=100 : Maximum number of violations to display per audit}';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /** @var string|null $subdirectory */
        $subdirectory = $this->argument('directory');

        $auditsPath = AuditManager::getAuditsPath();
        $basePath = $this->isAbsolutePath($auditsPath)
            ? $auditsPath
            : base_path($auditsPath);
        $discovery = new AuditDiscovery($basePath);
        $auditClasses = $discovery->discover($subdirectory);

        if ($auditClasses->isEmpty()) {
            $label = $subdirectory ? "No audits found in {$subdirectory}." : 'No audits found.';
            $this->line("  <fg=yellow>$label</>");
            $this->newLine();

            return;
        }

        $audits = $this->collectAudits($auditClasses);

        /** @var string|null $modelFilter */
        $modelFilter = $this->option('model');

        if ($modelFilter) {
            $audits = array_values(array_filter(
                $audits,
                fn (Audit $audit) => class_basename($audit->getModel()) === $modelFilter,
            ));
        }

        /** @var string|null $descriptionFilter */
        $descriptionFilter = $this->option('filter');

        if ($descriptionFilter) {
            $audits = array_values(array_filter(
                $audits,
                fn (Audit $audit) => str_contains(
                    strtolower($audit->getDescription() ?? ''),
                    strtolower($descriptionFilter),
                ),
            ));
        }

        if (empty($audits)) {
            $label = $modelFilter
                ? "No audits found for model {$modelFilter}."
                : ($descriptionFilter
                    ? "No audits found matching \"{$descriptionFilter}\"."
                    : 'No audits found.');
            $this->line("  <fg=yellow>$label</>");
            $this->newLine();

            return;
        }

        $this->runAudits($audits);
    }

    /**
     * Instantiate each AuditCase class and collect all Audits.
     *
     * @param  Collection<int, class-string<AuditCase>>  $auditClasses
     * @return Audit[]
     */
    protected function collectAudits(Collection $auditClasses): array
    {
        $audits = [];

        foreach ($auditClasses as $class) {
            $auditCase = new $class;

            $audits = array_merge($audits, $auditCase->getAudits());
        }

        return $audits;
    }

    /**
     * Execute the collected audits.
     *
     * @param  Audit[]  $audits
     */
    protected function runAudits(array $audits): void
    {
        $startTime = microtime(true);

        $this->newLine();

        $maxDisplayedViolations = (int) $this->option('max-violations');

        /** @var array<int, array{reasons: list<string>, total: int, fixed: int}> $allViolations */
        $allViolations = [];

        $grouped = collect($audits)->groupBy(fn (Audit $audit) => $audit->getModel());

        foreach ($grouped as $modelClass => $modelAudits) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
            $shortName = class_basename($modelClass);

            $this->line("  <options=bold>$shortName</>");

            foreach ($modelAudits as $audit) {
                $key = spl_object_id($audit);
                $allViolations[$key] = ['reasons' => [], 'total' => 0, 'fixed' => 0];

                $query = $modelClass::query();

                if ($audit->getQueryCallback()) {
                    ($audit->getQueryCallback())($query);
                }

                $total = (clone $query)->count();

                $this->line("  … {$audit->getDescription()}");
                $progress = $this->createProgressBar($total);

                $progress->start();

                $shouldFix = $this->option('fix');

                $violations = &$allViolations[$key];

                $query->chunk($audit->getChunkSize(), function (Collection $chunk) use ($audit, &$violations, $progress, $shouldFix, $maxDisplayedViolations) {
                    if ($audit->getBeforeCallback()) {
                        ($audit->getBeforeCallback())($chunk);
                    }

                    foreach ($chunk as $model) {
                        $modelName = class_basename($model);
                        $modelKey = $model->getKey();

                        $fail = function (string $reason, ?Closure $fix = null) use (&$violations, $modelName, $modelKey, $shouldFix, $maxDisplayedViolations) {
                            /** @var array{reasons: list<string>, total: int, fixed: int} $violations */
                            $violations['total']++;

                            if (count($violations['reasons']) < $maxDisplayedViolations) {
                                $violations['reasons'][] = "{$modelName} #{$modelKey}: {$reason}";
                            }

                            if ($fix) {
                                if ($shouldFix) {
                                    $fix();
                                }

                                $violations['fixed']++;
                            }
                        };

                        if ($audit->getValidateCallback()) {
                            ($audit->getValidateCallback())($model, $fail);
                        }
                    }

                    if ($audit->getAfterCallback()) {
                        ($audit->getAfterCallback())($chunk);
                    }

                    $progress->advance($chunk->count());
                });

                $progress->finish();

                $this->output->write("\r\033[K");
                $this->output->write("\033[1A\033[K");

                /** @var array<int, array{reasons: list<string>, total: int, fixed: int}> $allViolations */
                $stats = $allViolations[$key];
                $passed = $stats['total'] === 0;
                $icon = $passed ? '<fg=green>✓</>' : '<fg=red>✗</>';

                $resultLines = ["  $icon {$audit->getDescription()}"];

                if (! $passed) {
                    foreach ($stats['reasons'] as $i => $reason) {
                        $number = $i + 1;
                        $resultLines[] = "    <fg=red>$number)</> $reason";
                    }

                    if ($stats['total'] > count($stats['reasons'])) {
                        $remaining = $stats['total'] - count($stats['reasons']);
                        $resultLines[] = "    <fg=gray>… and $remaining more</>";
                    }

                    $resultLines[] = "    <fg=red>→ {$stats['total']} ".Str::plural('error', $stats['total']).' found</>';

                    if ($stats['fixed'] > 0) {
                        if ($this->option('fix')) {
                            $resultLines[] = "    <fg=cyan>→ Fixed {$stats['fixed']} ".Str::plural('record', $stats['fixed']).'</>';
                        } else {
                            $resultLines[] = "    <fg=yellow>→ {$stats['fixed']} ".Str::plural('record', $stats['fixed']).' can be fixed automatically</>';
                        }
                    }
                }

                foreach ($resultLines as $resultLine) {
                    $this->line($resultLine);
                }
            }

            $this->newLine();
        }

        $this->printSummary($audits, $allViolations, $startTime);
    }

    /**
     * Print the final summary line with totals and elapsed time.
     *
     * @param  Audit[]  $audits
     * @param  array<int, array{reasons: list<string>, total: int, fixed: int}>  $allViolations
     */
    protected function printSummary(array $audits, array $allViolations, float $startTime): void
    {
        $totalAudits = count($audits);
        $failedCount = 0;
        $totalFailures = 0;
        $totalFixed = 0;

        foreach ($audits as $audit) {
            $stats = $allViolations[spl_object_id($audit)];

            if ($stats['total'] > 0) {
                $failedCount++;
                $totalFailures += $stats['total'];
                $totalFixed += $stats['fixed'];
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        if ($failedCount === 0) {
            $this->line("Audits:   <fg=green;options=bold>$totalAudits passed</>");
        } elseif ($totalFixed > 0) {
            if ($this->option('fix')) {
                $this->line("Audits:   <fg=red;options=bold>$failedCount failed</> ($totalFailures ".Str::plural('record', $totalFailures)."), $totalAudits total — <fg=cyan>$totalFixed ".Str::plural('record', $totalFixed).' fixed</>');
            } else {
                $this->line("Audits:   <fg=red;options=bold>$failedCount failed</> ($totalFailures ".Str::plural('record', $totalFailures)."), $totalAudits total — <fg=yellow>$totalFixed ".Str::plural('record', $totalFixed).' can be fixed automatically</>');
            }
        } else {
            $this->line("Audits:   <fg=red;options=bold>$failedCount failed</> ($totalFailures ".Str::plural('record', $totalFailures)."), $totalAudits total");
        }

        $this->line("Duration: <fg=default>{$duration}s</>");
        $this->newLine();

        if ($failedCount > 0 && ! $this->option('fix')) {
            $this->line('Run with <options=bold>--fix</> to resolve failures automatically.');
            $this->newLine();
        }
    }

    /**
     * Create a styled progress bar for the given total.
     */
    protected function createProgressBar(int $total): ProgressBar
    {
        $progress = $this->output->createProgressBar($total);
        $progress->setFormat('  %percent:3s%% [%bar%] %current%/%max%');
        $progress->setBarCharacter('<fg=green>█</>');
        $progress->setEmptyBarCharacter('<fg=gray>░</>');
        $progress->setProgressCharacter('<fg=green>█</>');

        return $progress;
    }

    /**
     * Determine if the given path is absolute.
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path);
    }
}
