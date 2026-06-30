<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Illuminate\View\View;
use Illuminate\Http\Request; // Tambahkan ini
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nests\NestUpdateService;
use Pterodactyl\Services\Nests\NestCreationService;
use Pterodactyl\Services\Nests\NestDeletionService;
use Pterodactyl\Contracts\Repository\NestRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Nest\StoreNestFormRequest;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class NestController extends Controller
{
    /**
     * NestController constructor.
     */
    public function __construct(
        protected AlertsMessageBag $alert,
        protected NestCreationService $nestCreationService,
        protected NestDeletionService $nestDeletionService,
        protected NestRepositoryInterface $repository,
        protected NestUpdateService $nestUpdateService,
        protected ViewFactory $view,
    ) {
    }

    /**
     * Helper untuk validasi akses ID 1
     */
    protected function validateOwner(Request $request)
    {
        if ($request->user()->id !== 1) {
            throw new AccessDeniedHttpException('Akses Ditolak: Manajemen Nest hanya untuk Super Admin (ID 1).');
        }
    }

    /**
     * Render nest listing page.
     */
    public function index(): View
    {
        return view('admin.nests.index', [
            'nests' => $this->repository->getWithCounts(),
        ]);
    }

    /**
     * Render nest creation page.
     */
    public function create(Request $request): View
    {
        $this->validateOwner($request);

        return view('admin.nests.new');
    }

    /**
     * Handle the storage of a new nest.
     */
    public function store(StoreNestFormRequest $request): RedirectResponse
    {
        $this->validateOwner($request);

        $nest = $this->nestCreationService->handle($request->normalize());
        $this->alert->success(trans('admin/nests.notices.created', ['name' => htmlspecialchars($nest->name)]))->flash();

        return redirect()->route('admin.nests.view', $nest->id);
    }

    /**
     * Return details about a nest including all the eggs and servers per egg.
     */
    public function view(int $nest): View
    {
        return view('admin.nests.view', [
            'nest' => $this->repository->getWithEggServers($nest),
        ]);
    }

    /**
     * Handle request to update a nest.
     */
    public function update(StoreNestFormRequest $request, int $nest): RedirectResponse
    {
        $this->validateOwner($request);

        $this->nestUpdateService->handle($nest, $request->normalize());
        $this->alert->success(trans('admin/nests.notices.updated'))->flash();

        return redirect()->route('admin.nests.view', $nest);
    }

    /**
     * Handle request to delete a nest.
     */
    public function destroy(Request $request, int $nest): RedirectResponse
    {
        $this->validateOwner($request);

        $this->nestDeletionService->handle($nest);
        $this->alert->success(trans('admin/nests.notices.deleted'))->flash();

        return redirect()->route('admin.nests');
    }
}
