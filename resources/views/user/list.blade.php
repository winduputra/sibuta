@extends('layouts.admin')

@section('title', 'List Karyawan')

@push('style')
<link rel="stylesheet" href="{{ url('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" href="{{ url('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
<link rel="stylesheet" href="{{ url('plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">
@endpush

@section('content')

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Karyawan</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item active"><a>Karyawan</a></li>
        </ol>
      </div>
    </div>
  </div>
</div>

<section class="content">
  <div class="container-fluid">
    
    <div class="row ">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center w-100">
              <h3 class="card-title">List Karyawan</h3>
              <a href="{{ route('user.add') }}" class="btn btn-sm btn-success font-weight-bold">
                Add
              </a>
            </div>
          </div>
          <!-- /.card-header -->
          <div class="card-body">

            @if ( session()->has('success') )
            <div class="alert alert-success">
                <p class="mb-0">{{ session()->get('success') }}</p>
            </div>
            @endif

            <table id="user" class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th>Picture</th>
                  <th>Name</th>
                  <th>Username</th>
                  <th>Division</th>
                  <th>#</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($users as $item)
                <tr>
                  <td>
                    @if ( $item->image )
                    <img src="{{ $item->image }}" alt="Image" style="height: 120px;">
                    @endif
                  </td>
                  <td>{{ $item->name }}</td>
                  <td>{{ $item->username }}</td>
                  <td>{{ $item->division->name }}</td>
                  <td>
                    <a href="{{ route('user.edit', ['id' => $item->id]) }}" class="btn btn-sm btn-success">
                        <i class="fa fa-edit"></i>
                    </a>

                    <button  data-id="{{ $item->id }}" data-name="{{{ $item->title }}}" class="btn btn-danger btn-sm btn-delete">
                        <i class="fa fa-trash"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr>
                  <th>Picture</th>
                  <th>Name</th>
                  <th>Username</th>
                  <th>Division</th>
                  <th>#</th>
                </tr>
              </tfoot>
            </table>
          </div>
          <!-- /.card-body -->
        </div>
      </div>

    </div>

  </div>
</section>

@endsection

@push('script')

<script src="{{ url('plugins/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ url('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ url('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ url('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
<script src="{{ url('plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
<script src="{{ url('plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
<script>
  $(function () {
    $('.btn-danger').click(function(event) {
      const id = $(this).data('id');
      
      var title = `Hapus Data`
      var text  = `Hapus Data User?`
      var icon  = `primary`
      var confirmButtonText   = 'Hapus!'
      var successText   = 'Berhasil menghapus Data!'

      event.preventDefault();

      Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Batal',
        reverseButtons: true,
      }).then(result => {
        if (result.isConfirmed) {
          $.ajax({
            url: `/user/delete/${id}`,
            type: 'POST',
            data: {
              id: id,
            },
            headers: {
                'X-CSRF-Token': '{{ csrf_token() }}'
            },
            success: function () {
              Swal.fire({
                title: 'Success',
                text: successText,
                icon: 'success',
              }).then(function() {
                location.reload();
              });
            },
            error: function (xhr, status, error) {
              var err = JSON.parse(xhr.responseText);
              Swal.fire({
                title: 'Error',
                text: err?.message,
                icon: 'error',
              });
            }
          })
        }
      });
    })

    $('#user').DataTable({
      "paging": true,
      "lengthChange": true,
      "searching": true,
      "ordering": true,
      "info": true,
      "autoWidth": false,
      "responsive": true,
      "order": [[3, 'asc'], [1, 'asc']],
      "iDisplayLength": 50,
      "rowCallback": function(settings, json) {
      }
    });
  });

</script>
@endpush
