<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

class NodeSystemUsageController extends Controller
{
    public function __construct(private DaemonServerRepository $repository)
    {
    }

    /**
     * Fetch real-time active usage by summing up all servers' live stats.
     */
    public function __invoke(Node $node): JsonResponse
    {
        $servers = $node->servers()->get(['uuid']);

        $cpuActive = 0;
        $memoryActive = 0;
        $diskActive = 0;

        foreach ($servers as $server) {
            try {
                $details = $this->repository->setServer($server)->getDetails();
                $utilization = $details['utilization'] ?? [];

                $cpuActive += $utilization['cpu_absolute'] ?? 0;
                $memoryActive += $utilization['memory_bytes'] ?? 0;
                $diskActive += $utilization['disk_bytes'] ?? 0;
            } catch (\Throwable) {
                continue;
            }
        }

        return new JsonResponse([
            'active' => [
                'cpu' => $cpuActive,
                'memory_bytes' => $memoryActive,
                'disk_bytes' => $diskActive,
            ],
        ]);
    }
}
