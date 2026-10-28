<?php

namespace App\Console\Commands;

use App\Services\ShopNotices;
use Illuminate\Console\Command;

class NotifyShops extends Command
{
    protected $signature = 'shops:notify';

    protected $description = 'Email shops whose trial or paid plan is ending or has ended';

    public function handle(ShopNotices $notices): int
    {
        $sent = $notices->sendDue();
        $this->info("Sent {$sent} shop notices.");

        return self::SUCCESS;
    }
}
