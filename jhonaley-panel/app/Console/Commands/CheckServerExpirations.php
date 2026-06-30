<?php

namespace Pterodactyl\Console\Commands;

use Carbon\Carbon;
use Pterodactyl\Models\Server;
use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\SuspensionService;

class CheckServerExpirations extends Command
{
    /**
     * Nama command yang dipanggil via terminal atau cronjob.
     * Contoh: php artisan ptero:check-expiration
     */
    protected $signature = 'ptero:check-expiration';

    /**
     * Deskripsi command yang muncul di daftar php artisan.
     */
    protected $description = 'Mengecek server yang melewati batas waktu expires_at dan men-suspend secara otomatis.';

    /**
     * @var \Pterodactyl\Services\Servers\SuspensionService
     */
    protected $suspensionService;

    /**
     * CheckServerExpirations constructor.
     */
    public function __construct(SuspensionService $suspensionService)
    {
        parent::__construct();
        $this->suspensionService = $suspensionService;
    }

    /**
     * Eksekusi logika utama.
     */
    public function handle()
{
    $this->info('ALXZEN PROTECT: Scanning...');

    $servers = \Pterodactyl\Models\Server::query()
        ->whereNotNull('expires_at')
        ->whereRaw('expires_at < NOW()') // Menggunakan jam server database langsung
        ->where(function ($query) {
            $query->where('status', '!=', \Pterodactyl\Models\Server::STATUS_SUSPENDED)
                  ->orWhereNull('status');
        })
        ->get();

    if ($servers->isEmpty()) {
        $this->info('STATUS: CLEAN');
        return;
    }

    foreach ($servers as $server) {
        try {
            $this->line("SUSPENDING: {$server->name}");
            $this->suspensionService->toggle($server, \Pterodactyl\Services\Servers\SuspensionService::ACTION_SUSPEND);
            $this->info("SUCCESS: {$server->name}");
        } catch (\Exception $exception) {
            $this->error("ERROR: " . $exception->getMessage());
            $server->update(['status' => \Pterodactyl\Models\Server::STATUS_SUSPENDED]);
        }
    }
}

}
