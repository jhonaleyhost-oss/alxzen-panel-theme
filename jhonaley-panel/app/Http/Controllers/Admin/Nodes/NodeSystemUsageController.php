<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class NodeSystemUsageController extends Controller
{
    /**
     * Fetch real-time active usage by summing up all servers' live stats via concurrent Wings API calls.
     */
    public function __invoke(Request $request, Node $node): JsonResponse
    {
        $servers = $node->servers()->get(['uuid']);
        
        $cpu_active = 0;
        $memory_active = 0;
        $disk_active = 0;

        if ($servers->isNotEmpty()) {
            $baseUrl = $node->getConnectionAddress();
            $token = $node->getDecryptedKey();
            $isProduction = app()->environment('production');

            // Send concurrent requests to Wings for all servers on this node
            $responses = Http::pool(function (Pool $pool) use ($servers, $baseUrl, $token, $isProduction) {
                return $servers->map(function ($server) use ($pool, $baseUrl, $token, $isProduction) {
                    $req = $pool->withToken($token)->acceptJson()->timeout(5);
                    if (!$isProduction) {
                        $req = $req->withoutVerifying();
                    }
                    return $req->get("$baseUrl/api/servers/{$server->uuid}");
                })->all();
            });

            foreach ($responses as $response) {
                if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                    $data = $response->json();
                    // state is running, starting, etc.
                    $cpu_active += $data['utilization']['cpu_absolute'] ?? 0;
                    $memory_active += $data['utilization']['memory_bytes'] ?? 0;
                    $disk_active += $data['utilization']['disk_bytes'] ?? 0;
                }
            }
        }

        // We convert active memory and disk to GiB and format.
        // Node limit memory/disk are in MB from DB.
        
        $cpu_max = $node->servers()->sum('cpu') ?: 100; // Total allocated CPU for percentage
        
        return new JsonResponse([
            'active' => [
                'cpu' => $cpu_active,
                'memory_bytes' => $memory_active,
                'disk_bytes' => $disk_active,
            ]
        ]);
    }
}
