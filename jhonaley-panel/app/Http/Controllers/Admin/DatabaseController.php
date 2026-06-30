<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request; // Tambahkan ini
use Pterodactyl\Models\DatabaseHost;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Databases\Hosts\HostUpdateService;
use Pterodactyl\Http\Requests\Admin\DatabaseHostFormRequest;
use Pterodactyl\Services\Databases\Hosts\HostCreationService;
use Pterodactyl\Services\Databases\Hosts\HostDeletionService;
use Pterodactyl\Contracts\Repository\DatabaseRepositoryInterface;
use Pterodactyl\Contracts\Repository\LocationRepositoryInterface;
use Pterodactyl\Contracts\Repository\DatabaseHostRepositoryInterface;

class DatabaseController extends Controller
{
    /**
     * DatabaseController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private DatabaseHostRepositoryInterface $repository,
        private DatabaseRepositoryInterface $databaseRepository,
        private HostCreationService $creationService,
        private HostDeletionService $deletionService,
        private HostUpdateService $updateService,
        private LocationRepositoryInterface $locationRepository,
    ) {
    }

    /**
     * Display database host index.
     */
    public function index(): View
    {
        return view('admin.databases.index', [
            'locations' => $this->locationRepository->getAllWithNodes(),
            'hosts' => $this->repository->getWithViewDetails(),
        ]);
    }

    /**
     * Display database host to user.
     */
    public function view(int $host): View
    {
        return view('admin.databases.view', [
            'locations' => $this->locationRepository->getAllWithNodes(),
            'host' => $this->repository->find($host),
            'databases' => $this->databaseRepository->getDatabasesForHost($host),
        ]);
    }

    /**
     * Handle request to create a new database host.
     * PROTECTED: Hanya ID 1 yang bisa menambah Host.
     */
    public function create(DatabaseHostFormRequest $request): RedirectResponse
    {
        if ($request->user()->id !== 1) {
            $this->alert->danger('Akses Ditolak: Hanya Super Admin yang bisa menambah Database Host!')->flash();
            return redirect()->route('admin.databases');
        }

        try {
            $host = $this->creationService->handle($request->normalize());
        } catch (\Exception $exception) {
            if ($exception instanceof \PDOException || $exception->getPrevious() instanceof \PDOException) {
                $this->alert->danger(
                    sprintf('There was an error while trying to connect to the host or while executing a query: "%s"', $exception->getMessage())
                )->flash();

                return redirect()->route('admin.databases')->withInput($request->validated());
            } else {
                throw $exception;
            }
        }

        $this->alert->success('Successfully created a new database host on the system.')->flash();

        return redirect()->route('admin.databases.view', $host->id);
    }

    /**
     * Handle updating database host.
     * PROTECTED: Hanya ID 1 yang bisa mengedit Host.
     */
    public function update(DatabaseHostFormRequest $request, DatabaseHost $host): RedirectResponse
    {
        if ($request->user()->id !== 1) {
            $this->alert->danger('Akses Ditolak: Anda tidak diizinkan mengubah konfigurasi Database Host!')->flash();
            return redirect()->route('admin.databases.view', $host->id);
        }

        $redirect = redirect()->route('admin.databases.view', $host->id);

        try {
            $this->updateService->handle($host->id, $request->normalize());
            $this->alert->success('Database host was updated successfully.')->flash();
        } catch (\Exception $exception) {
            if ($exception instanceof \PDOException || $exception->getPrevious() instanceof \PDOException) {
                $this->alert->danger(
                    sprintf('There was an error while trying to connect to the host or while executing a query: "%s"', $exception->getMessage())
                )->flash();

                return $redirect->withInput($request->normalize());
            } else {
                throw $exception;
            }
        }

        return $redirect;
    }

    /**
     * Handle request to delete a database host.
     * PROTECTED: Hanya ID 1 yang bisa menghapus Host.
     */
    public function delete(Request $request, int $host): RedirectResponse
    {
        if ($request->user()->id !== 1) {
            $this->alert->danger('Aksi Ilegal: Hanya Super Admin yang bisa menghapus Database Host!')->flash();
            return redirect()->route('admin.databases');
        }

        $this->deletionService->handle($host);
        $this->alert->success('The requested database host has been deleted from the system.')->flash();

        return redirect()->route('admin.databases');
    }
}
