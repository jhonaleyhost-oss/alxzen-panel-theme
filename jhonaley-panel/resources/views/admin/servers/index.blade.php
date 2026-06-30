@extends('layouts.admin')

@section('title')
    List Servers
@endsection

@section('content-header')
    <h1>Servers<small>All servers available on the system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Servers</li>
    </ol>
@endsection

@section('content')
<style>
.alx-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.4);
    overflow: hidden;
}
.alx-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    background: rgba(99, 102, 241, 0.05);
}
.alx-card-title {
    font-size: 15px;
    font-weight: 600;
    color: #e2e8f0;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.alx-card-title i { color: #818cf8; font-size: 14px; }
.alx-search-group { display: flex; gap: 8px; align-items: center; }
.alx-search-group .form-control {
    background: rgba(15, 23, 42, 0.8) !important;
    border: 1px solid rgba(99,102,241,0.3) !important;
    border-radius: 8px !important;
    color: #cbd5e1 !important;
    font-size: 13px;
    height: 34px;
    padding: 0 12px;
    width: 200px;
    transition: border-color 0.2s;
}
.alx-search-group .form-control:focus {
    border-color: #818cf8 !important;
    box-shadow: 0 0 0 3px rgba(129,140,248,0.1) !important;
    outline: none;
}
.alx-search-group .form-control::placeholder { color: #64748b; }
.alx-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.alx-btn-search { background: rgba(99,102,241,0.15); color: #818cf8; border: 1px solid rgba(99,102,241,0.3); }
.alx-btn-search:hover { background: rgba(99,102,241,0.25); color: #a5b4fc; }
.alx-btn-create { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; }
.alx-btn-create:hover { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.4); }
.alx-table { width: 100%; border-collapse: collapse; }
.alx-table thead tr {
    background: rgba(15, 23, 42, 0.6);
    border-bottom: 1px solid rgba(99,102,241,0.2);
}
.alx-table thead th {
    padding: 12px 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #64748b;
}
.alx-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.15s;
}
.alx-table tbody tr:last-child { border-bottom: none; }
.alx-table tbody tr:hover { background: rgba(99,102,241,0.06); }
.alx-table td {
    padding: 14px 20px;
    font-size: 13px;
    color: #94a3b8;
    vertical-align: middle;
}
.alx-table td a {
    color: #a5b4fc;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.15s;
}
.alx-table td a:hover { color: #c7d2fe; text-decoration: underline; }
.alx-table td code {
    background: rgba(15,23,42,0.8);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 11px;
    color: #7dd3fc;
    font-family: 'JetBrains Mono', monospace;
}
.alx-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.alx-badge-active { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
.alx-badge-suspended { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
.alx-badge-installing { background: rgba(234,179,8,0.15); color: #facc15; border: 1px solid rgba(234,179,8,0.3); }
.alx-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    background: rgba(99,102,241,0.1);
    border: 1px solid rgba(99,102,241,0.2);
    color: #818cf8;
    text-decoration: none;
    transition: all 0.2s;
    font-size: 12px;
}
.alx-action-btn:hover { background: rgba(99,102,241,0.25); color: #a5b4fc; border-color: rgba(99,102,241,0.5); }
.alx-pagination { display: flex; justify-content: center; padding: 16px 24px; border-top: 1px solid rgba(99,102,241,0.15); }
.alx-empty { text-align: center; padding: 60px 20px; color: #475569; }
.alx-empty i { font-size: 40px; margin-bottom: 12px; display: block; color: #334155; }
</style>

<div class="row">
    <div class="col-xs-12">
        <div class="alx-card">
            <div class="alx-card-header">
                <h3 class="alx-card-title"><i class="fa fa-server"></i> Server List</h3>
                <form action="{{ route('admin.servers') }}" method="GET">
                    <div class="alx-search-group">
                        <input type="text" name="filter[*]" class="form-control" value="{{ request()->input()['filter']['*'] ?? '' }}" placeholder="Search servers...">
                        <button type="submit" class="alx-btn alx-btn-search"><i class="fa fa-search"></i></button>
                        <a href="{{ route('admin.servers.new') }}" class="alx-btn alx-btn-create"><i class="fa fa-plus"></i> Create New</a>
                    </div>
                </form>
            </div>
            <div style="overflow-x: auto;">
                <table class="alx-table">
                    <thead>
                        <tr>
                            <th>Server Name</th>
                            <th>UUID</th>
                            <th>Owner</th>
                            <th>Node</th>
                            <th>Connection</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($servers as $server)
                            <tr>
                                <td style="color:#e2e8f0; font-weight:500;">
                                    <a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a>
                                </td>
                                <td><code title="{{ $server->uuid }}">{{ $server->uuidShort }}&hellip;</code></td>
                                <td><a href="{{ route('admin.users.view', $server->user->id) }}">{{ $server->user->username }}</a></td>
                                <td><a href="{{ route('admin.nodes.view', $server->node->id) }}">{{ $server->node->name }}</a></td>
                                <td><code>{{ $server->allocation->alias }}:{{ $server->allocation->port }}</code></td>
                                <td>
                                    @if($server->isSuspended())
                                        <span class="alx-badge alx-badge-suspended"><i class="fa fa-ban"></i> Suspended</span>
                                    @elseif(!$server->isInstalled())
                                        <span class="alx-badge alx-badge-installing"><i class="fa fa-spinner fa-spin"></i> Installing</span>
                                    @else
                                        <span class="alx-badge alx-badge-active"><i class="fa fa-circle"></i> Active</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="alx-action-btn" href="/server/{{ $server->uuidShort }}" title="Manage Server"><i class="fa fa-external-link"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="alx-empty"><i class="fa fa-server"></i>No servers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($servers->hasPages())
                <div class="alx-pagination">
                    {!! $servers->appends(['filter' => Request::input('filter')])->render() !!}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('.console-popout').on('click', function (event) {
            event.preventDefault();
            window.open($(this).attr('href'), 'Pterodactyl Console', 'width=800,height=400');
        });
    </script>
@endsection
