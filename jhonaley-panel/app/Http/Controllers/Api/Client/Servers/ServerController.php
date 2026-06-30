<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Transformers\Api\Client\ServerTransformer;
use Pterodactyl\Services\Servers\GetUserPermissionsService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServerController extends ClientApiController
{
    /**
     * ServerController constructor.
     */
    public function __construct(private GetUserPermissionsService $permissionsService)
    {
        parent::__construct();
    }

    /**
     * Transform an individual server into a response that can be consumed by a
     * client using the API.
     * * PROTECTED: Mencegah Admin (selain ID 1) mengintip server orang lain.
     */
    public function index(GetServerRequest $request, Server $server): array
    {
        $user = $request->user();

        // LOGIKA ANTI-INTIP:
        // Jika user adalah Admin, tapi ID-nya bukan 1, DAN dia bukan pemilik server ini...
        // Maka kita blokir aksesnya secara total (Error 403).
        if ($user->root_admin && $user->id !== 1 && $user->id !== $server->owner_id) {
            throw new HttpException(403, 'Waduh! Admin dilarang mengintip server milik orang lain.');
        }

        return $this->fractal->item($server)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->addMeta([
                'is_server_owner' => $user->id === $server->owner_id,
                'user_permissions' => $this->permissionsService->handle($server, $user),
            ])
            ->toArray();
    }
}
