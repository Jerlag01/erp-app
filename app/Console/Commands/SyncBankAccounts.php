<?php

namespace App\Console\Commands;

use App\Actions\Banking\ImportBankTransactions;
use App\Actions\Banking\MatchBankTransactions;
use App\Services\Banking\PontoClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('banking:sync')]
#[Description('Read the bank accounts through Ponto and check the movements against the open invoices (read only)')]
class SyncBankAccounts extends Command
{
    public function handle(ImportBankTransactions $import, MatchBankTransactions $matcher): int
    {
        if (! modules()->finance()) {
            $this->components->info('The finance module is off.');

            return self::SUCCESS;
        }

        if (PontoClient::isConnected()) {
            try {
                $result = $import->fromPonto();
                $this->components->info("Ponto: {$result['accounts']} account(s), {$result['added']} new movement(s), {$result['matched']} matched.");

                foreach ($result['errors'] as $error) {
                    $this->components->error($error);
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error('Ponto: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        // Invoices finalised since the last run can match movements that came in earlier.
        $matched = $matcher->run();
        $this->components->info("{$matched} earlier movement(s) matched.");

        return self::SUCCESS;
    }
}
