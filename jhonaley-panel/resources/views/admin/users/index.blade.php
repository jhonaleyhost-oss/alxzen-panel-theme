@extends('layouts.admin')

@section('title')
    List Users
@endsection

@section('content-header')
    <h1>Users<small>All registered users on the system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Users</li>
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
    font-size: 15px; font-weight: 600; color: #e2e8f0;
    letter-spacing: 0.3px; display: flex; align-items: center; gap: 8px; margin: 0;
}
.alx-card-title i { color: #818cf8; font-size: 14px; }
.alx-search-group { display: flex; gap: 8px; align-items: center; }
.alx-search-group .form-control {
    background: rgba(15,23,42,0.8) !important;
    border: 1px solid rgba(99,102,241,0.3) !important;
    border-radius: 8px !important;
    color: #cbd5e1 !important;
    font-size: 13px; height: 34px; padding: 0 12px; width: 200px; transition: border-color 0.2s;
}
.alx-search-group .form-control:focus {
    border-color: #818cf8 !important; box-shadow: 0 0 0 3px rgba(129,140,248,0.1) !important; outline: none;
}
.alx-search-group .form-control::placeholder { color: #64748b; }
.alx-btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px;
    border-radius: 8px; font-size: 12px; font-weight: 500; border: none; cursor: pointer;
    transition: all 0.2s; text-decoration: none;
}
.alx-btn-search { background: rgba(99,102,241,0.15); color: #818cf8; border: 1px solid rgba(99,102,241,0.3); }
.alx-btn-search:hover { background: rgba(99,102,241,0.25); color: #a5b4fc; }
.alx-btn-create { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; }
.alx-btn-create:hover { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.4); }
.alx-table { width: 100%; border-collapse: collapse; }
.alx-table thead tr { background: rgba(15,23,42,0.6); border-bottom: 1px solid rgba(99,102,241,0.2); }
.alx-table thead th {
    padding: 12px 20px; font-size: 11px; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.8px; color: #64748b;
}
.alx-table tbody tr { border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.15s; }
.alx-table tbody tr:last-child { border-bottom: none; }
.alx-table tbody tr:hover { background: rgba(99,102,241,0.06); }
.alx-table td { padding: 13px 20px; font-size: 13px; color: #94a3b8; vertical-align: middle; }
.alx-table td a { color: #a5b4fc; text-decoration: none; font-weight: 500; transition: color 0.15s; }
.alx-table td a:hover { color: #c7d2fe; }
.alx-table td code {
    background: rgba(15,23,42,0.8); border: 1px solid rgba(99,102,241,0.2);
    border-radius: 4px; padding: 2px 6px; font-size: 11px; color: #7dd3fc;
}
.alx-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    border: 2px solid rgba(99,102,241,0.3);
    object-fit: cover;
}
.alx-badge-admin {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 600;
    background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3);
    margin-left: 6px;
}
.alx-stat-pill {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 28px; height: 22px; padding: 0 8px;
    border-radius: 12px; font-size: 11px; font-weight: 600;
    background: rgba(99,102,241,0.12); color: #818cf8;
    border: 1px solid rgba(99,102,241,0.2);
    text-decoration: none !important; transition: all 0.15s;
}
.alx-stat-pill:hover { background: rgba(99,102,241,0.25); color: #a5b4fc; }
.alx-2fa-on { color: #4ade80; }
.alx-2fa-off { color: #f87171; }
.alx-pagination { display: flex; justify-content: center; padding: 16px 24px; border-top: 1px solid rgba(99,102,241,0.15); }
</style>

<div class="row">
    <div class="col-xs-12">
        <div class="alx-card">
            <div class="alx-card-header">
                <h3 class="alx-card-title"><i class="fa fa-users"></i> User List</h3>
                <form action="{{ route('admin.users') }}" method="GET">
                    <div class="alx-search-group">
                        <input type="text" name="filter[email]" class="form-control" value="{{ request()->input('filter.email') }}" placeholder="Search by email...">
                        <button type="submit" class="alx-btn alx-btn-search"><i class="fa fa-search"></i></button>
                        <a href="{{ route('admin.users.new') }}" class="alx-btn alx-btn-create"><i class="fa fa-plus"></i> Create New</a>
                    </div>
                </form>
            </div>
            <div style="overflow-x: auto;">
                <table class="alx-table">
                    <thead>
                        <tr>
                            <th>Avatar</th>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th style="text-align:center">2FA</th>
                            <th style="text-align:center" data-toggle="tooltip" title="Servers owned by this user">Servers</th>
                            <th style="text-align:center" data-toggle="tooltip" title="Servers accessible as subuser">Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td style="width:50px">
                                    <img src="https://www.gravatar.com/avatar/{{ md5(strtolower($user->email)) }}?s=64&d=mp" class="alx-avatar" alt="{{ $user->username }}">
                                </td>
                                <td><code>{{ $user->id }}</code></td>
                                <td>
                                    <a href="{{ route('admin.users.view', $user->id) }}">{{ $user->email }}</a>
                                    @if($user->root_admin)
                                        <span class="alx-badge-admin"><i class="fa fa-star"></i> Admin</span>
                                    @endif
                                </td>
                                <td style="color:#e2e8f0">{{ $user->name_last }}, {{ $user->name_first }}</td>
                                <td>{{ $user->username }}</td>
                                <td style="text-align:center">
                                    @if($user->use_totp)
                                        <i class="fa fa-lock alx-2fa-on" title="2FA Enabled" data-toggle="tooltip"></i>
                                    @else
                                        <i class="fa fa-unlock alx-2fa-off" title="2FA Disabled" data-toggle="tooltip"></i>
                                    @endif
                                </td>
                                <td style="text-align:center">
                                    <a href="{{ route('admin.servers', ['filter[owner_id]' => $user->id]) }}" class="alx-stat-pill">{{ $user->servers_count }}</a>
                                </td>
                                <td style="text-align:center">
                                    <span class="alx-stat-pill">{{ $user->subuser_of_count }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="text-align:center;padding:60px;color:#475569"><i class="fa fa-users" style="font-size:36px;display:block;margin-bottom:10px;color:#334155"></i>No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="alx-pagination">
                    {!! $users->appends(['query' => Request::input('query')])->render() !!}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
