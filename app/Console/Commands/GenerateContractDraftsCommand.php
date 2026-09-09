<?php

namespace App\Console\Commands;

use App\Actions\Contracts\GenerateContractDrafts;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The scheduled half of monthly invoicing: every morning, every active company
 * gets the drafts its contracts owe for that day. Nothing is issued — somebody
 * still has to look at them.
 *
 * The same work is available from the screen, because a scheduler is not always
 * running on a development machine, and because a missed day has to be catchable
 * by hand.
 */
class GenerateContractDraftsCommand extends Command
{
    protected $signature = 'contracts:generate-drafts
                            {--on= : Datum za koji se pravi obračun (YYYY-MM-DD), podrazumevano danas}
                            {--company= : ID pravnog lica, podrazumevano sva}
                            {--dry-run : Samo prikaži šta bi bilo napravljeno}';

    protected $description = 'Pravi nacrte faktura iz ugovora koji dospevaju na zadati dan';

    public function handle(GenerateContractDrafts $drafts): int
    {
        $on = $this->option('on') ? Carbon::parse($this->option('on')) : Carbon::now();
        $dryRun = (bool) $this->option('dry-run');

        $companies = Company::query()
            ->active()
            ->when($this->option('company'), fn ($query) => $query->whereKey($this->option('company')))
            ->orderBy('name')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Nema aktivnih pravnih lica.');

            return self::SUCCESS;
        }

        $this->line(sprintf('Obračun na dan %s%s', $on->format('d.m.Y.'), $dryRun ? ' (proba)' : ''));

        $total = 0;

        foreach ($companies as $company) {
            if ($dryRun) {
                $rows = $drafts->preview($company, $on);

                foreach ($rows as $row) {
                    $this->line(sprintf(
                        '  %s · %s · %s · %s',
                        $company->displayName(),
                        $row['contract']->name,
                        $row['contract']->periodLabel($row['period']),
                        $row['blocked'] ?? number_format($row['total'], 2, ',', '.').' '.$row['contract']->currency,
                    ));
                }

                $total += $rows->count();

                continue;
            }

            $result = $drafts->handle($company, $on);

            foreach ($result['created'] as $invoice) {
                $this->line(sprintf(
                    '  %s · %s · %s',
                    $company->displayName(),
                    $invoice->partner->name,
                    $invoice->periodLabel(),
                ));
            }

            foreach ($result['skipped'] as $skipped) {
                $this->warn(sprintf('  preskočeno: %s — %s', $skipped['contract']->name, $skipped['reason']));
            }

            $total += $result['created']->count();
        }

        $this->info(sprintf('%s %d.', $dryRun ? 'Bilo bi napravljeno nacrta:' : 'Napravljeno nacrta:', $total));

        return self::SUCCESS;
    }
}
