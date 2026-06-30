@extends('layouts.admin')

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Administrative Overview<small>A quick glance at your system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Index</li>
    </ol>
@endsection

@section('content')
<div class="row">
    {{-- Box Informasi Sistem Alxzen --}}
    <div class="col-md-6">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> System Information</h3>
            </div>
            <div class="box-body">
                You are running Alxzen Panel version <code>{{ config('app.version') }}</code>. Your panel is up-to-date!
            </div>
        </div>
    </div>

    {{-- Box Informasi Custom Branding (alxzen) --}}
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-shield"></i> Alxzen Branding & Protection</h3>
            </div>
            <div class="box-body">
                <ul class="list-unstyled">
                    <li><strong>Developed by:</strong> <span class="label label-primary">{{ config('app.author') }}</span></li>
                    <li><strong>Theme Version:</strong> <code>v{{ config('app.theme_version') }}</code></li>
                    <li><strong>Protection System:</strong> <span class="label label-success">Active (v{{ config('app.protect_version') }})</span></li>
                    <li><strong>Expiration Status:</strong> <code>v{{ config('app.expiration_version') }}</code></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDiscord() }}"><button class="btn btn-warning" style="width:100%;"><i class="fa fa-fw fa-support"></i> Get Help <small>(via Discord)</small></button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://pterodactyl.io"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-link"></i> Documentation</button></a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://github.com/pterodactyl/panel"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-support"></i> GitHub</button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDonations() }}"><button class="btn btn-success" style="width:100%;"><i class="fa fa-fw fa-money"></i> Support the Project</button></a>
    </div>
</div>
</div>

<div class="row">
    <div class="col-md-12 text-center" style="margin-top: 20px;">
        <p class="text-muted"><strong>Credits: Based on Pterodactyl</strong></p>
    </div>
</div>
@endsection
