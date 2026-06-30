@extends('layouts.admin')

@section('title')
    Expiration Manager
@endsection

@section('content-header')
    <h1>Expiration Manager<small>Manage server expiration dates.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Expiration Manager</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Server List</h3>
                <div class="box-tools search01">
                    <form action="{{ route('admin.expiration') }}" method="GET">
                        <div class="input-group input-group-sm">
                            <input type="text" name="filter[*]" class="form-control pull-right" value="{{ request()->input()['filter']['*'] ?? '' }}" placeholder="Search Server Name">
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Server Name</th>
                            <th>Expired At</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servers as $server)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a>
                                </td>

                                <td>
                                    @if($server->expires_at)
                                        {{ $server->expires_at->format('d M Y H:i') }}
                                    @else
                                        <span class="text-muted">Unlimited</span>
                                    @endif
                                </td>

                                <td>
                                    @if(!$server->expires_at)
                                        <span class="label label-default">Unlimited</span>
                                    @elseif($server->expires_at->isPast())
                                        <span class="label label-danger">EXPIRED</span>
                                    @else
                                        <span class="label label-success">
                                            {{ $server->expires_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </td>

                                <td class="text-right">
                                    <form action="{{ route('admin.expiration.update', $server->id) }}" method="POST">
                                        {!! csrf_field() !!}
                                        <button type="submit" class="btn btn-xs btn-primary">
                                            <i class="fa fa-plus-circle"></i> +30 Days
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($servers->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $servers->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection