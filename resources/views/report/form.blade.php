@extends('layouts.admin')

@section('title',( ( !empty($user) ) ? 'Edit' : 'Add') . ' Report')

@section('content')

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item active">Report</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<section class="content">
  <div class="container-fluid">
    
    <div class="row justify-content-center">
      <div class="col-md-6 col-offset-md-6">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Report</h3>
          </div>
          <form method="get" action="{{ route('report.download') }}">
            @csrf
            <div class="card-body">
              
              <div class="form-group">
                <label for="start_at" class="mb-0">Tanggal Mulai</label>
                <input type="date" class="form-control {{ $errors->has('start_at') ? 'is-invalid' : '' }}" id="start_at" name="start_at" placeholder="Tanggal Mulai" required value="{{ date('Y-m-') . '01' }}">
                @foreach($errors->get('start_at') as $msg)
                  <span class="text-danger text-small">{{ $msg }}</span>
                @endforeach
              </div>

              <div class="form-group">
                <label for="end_at" class="mb-0">Tanggal Akhir</label>
                <input type="date" class="form-control {{ $errors->has('end_at') ? 'is-invalid' : '' }}" id="end_at" name="end_at" placeholder="Tanggal Akhir" required value="{{ date('Y-m-t') }}">
                @foreach($errors->get('end_at') as $msg)
                  <span class="text-danger text-small">{{ $msg }}</span>
                @endforeach
              </div>

            </div>
            <!-- /.card-body -->

            <div class="card-footer">
              <button type="submit" class="btn btn-success">Download</button>
            </div>
          </form>
        </div>

      </div>

    </div>

  </div>
</section>

@endsection
