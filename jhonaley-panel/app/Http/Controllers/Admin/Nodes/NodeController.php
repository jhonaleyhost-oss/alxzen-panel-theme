<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Spatie\QueryBuilder\QueryBuilder;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class NodeController extends Controller
{
    /**
     * Returns a listing of nodes on the system.
     * * PROTECTED: Mencegah admin tambahan mengintip detail infrastruktur jika bukan ID 1.
     */
    public function index(Request $request): View
    {
        // Jika kamu ingin admin lain benar-benar tidak bisa melihat daftar Node sama sekali:
        if ($request->user()->id !== 1) {
            throw new AccessDeniedHttpException('Akses Ditolak: Anda tidak memiliki izin untuk melihat daftar Node.');
        }

        $nodes = QueryBuilder::for(
            Node::query()->with('location')->withCount('servers')
        )
            ->allowedFilters(['uuid', 'name'])
            ->allowedSorts(['id'])
            ->paginate(25);

        return view('admin.nodes.index', ['nodes' => $nodes]);
    }
}
