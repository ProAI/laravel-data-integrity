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
                {--fix : Fix issues automatically}';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
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

        $modelFilter = $this->option('model');

        if ($modelFilter) {
            $audits = array_values(array_filter(
                $audits,
                fn (Audit $audit) => class_basename($audit->getModel()) === $modelFilter,
            ));
        }

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
     * @return void
     */
    protected function runAudits(array $audits): void
    {
        $startTime = microtime(true);

        $this->newLine();

        $allViolations = [];

        $grouped = collect($audits)->groupBy(fn (Audit $audit) => $audit->getModel());

        foreach ($grouped as $modelClass => $modelAudits) {
            $shortName = class_basename($modelClass);

            $this->line("  <options=bold>$shortName</>");

            foreach ($modelAudits as $audit) {
                $key = spl_object_id($audit);
                $allViolations[$key] = collect();

                $query = $modelClass::query();

                if ($audit->getQueryCallback()) {
                    ($audit->getQueryCallback())($query);
                }

                $total = (clone $query)->count();
                $progress = $this->createProgressBar($total);
                $progress->start();

                $shouldFix = $this->option('fix');

                $query->chunk($audit->getChunkSize(), function ($chunk) use ($audit, $key, &$allViolations, $progress, $shouldFix) {
                    if ($audit->getBeforeCallback()) {
                        ($audit->getBeforeCallback())($chunk);
                    }

                    foreach ($chunk as $model) {
                        $modelName = class_basename($model);
                        $modelKey = $model->getKey();

                        $fail = function (string $reason, ?Closure $fix = null) use (&$allViolations, $key, $modelName, $modelKey, $shouldFix) {
                            $allViolations[$key]->push([
                                'reason' => "{$modelName} #{$modelKey}: {$reason}",
                                'fix' => $fix,
                            ]);

                            if ($shouldFix && $fix) {
                                $fix();
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
            }

            $suiteHasFailed = $modelAudits
                ->contains(fn (Audit $audit) => $allViolations[spl_object_id($audit)]->isNotEmpty());

            $badge = $suiteHasFailed
                ? '  <fg=black;bg=red> FAIL </>'
                : '  <fg=black;bg=green> PASS </>';

            $this->output->write("\033[1A");
            $this->line("$badge <options=bold>$shortName</>     ");

            foreach ($modelAudits as $audit) {
                $violations = $allViolations[spl_object_id($audit)];
                $passed = $violations->isEmpty();
                $icon = $passed ? '<fg=green>✓</>' : '<fg=red>✗</>';

                $this->line("  $icon {$audit->getDescription()}");

                if (! $passed) {
                    foreach ($violations->pluck('reason') as $i => $reason) {
                        $number = $i + 1;
                        $this->line("    <fg=red>$number)</> $reason");
                    }

                    $fixedCount = $violations->filter(fn ($v) => $v['fix'] !== null)->count();

                    if ($this->option('fix') && $fixedCount > 0) {
                        $this->line("    <fg=cyan>→ Fixed $fixedCount ".Str::plural('record', $fixedCount).'</>');
                    }
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
     * @param  array<int, Collection>  $allViolations
     * @return void
     */
    protected function printSummary(array $audits, array $allViolations, float $startTime): void
    {
        $totalAudits = count($audits);
        $failedCount = 0;
        $totalFailures = 0;
        $totalFixed = 0;

        foreach ($audits as $audit) {
            $violations = $allViolations[spl_object_id($audit)];

            if ($violations->isNotEmpty()) {
                $failedCount++;
                $totalFailures += $violations->count();
                $totalFixed += $violations->filter(fn ($v) => $v['fix'] !== null)->count();
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        if ($failedCount === 0) {
            $this->line("Audits:   <fg=green;options=bold>$totalAudits passed</>");
        } elseif ($this->option('fix') && $totalFixed > 0) {
            $this->line("Audits:   <fg=red;options=bold>$failedCount failed</> ($totalFailures ".Str::plural('record', $totalFailures)."), $totalAudits total — <fg=cyan>$totalFixed ".Str::plural('record', $totalFixed).' fixed</>');
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
     *
     * @return \Symfony\Component\Console\Helper\ProgressBar
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
     *
     * @return bool
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path);
    }
}
