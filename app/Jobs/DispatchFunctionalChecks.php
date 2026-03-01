<?php

namespace App\Jobs;

use App\Models\FunctionalCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchFunctionalChecks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('monitors');
    }

    public function handle(): void
    {
        FunctionalCheck::dueForCheck()
            ->get()
            ->each(fn (FunctionalCheck $check) => RunFunctionalCheck::dispatch($check));
    }
}
