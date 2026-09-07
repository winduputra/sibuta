@extends('layouts.admin')

@push('style')
{{-- <Name href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> --}}
@endpush

@section('title',( ( !empty($user) ) ? 'Edit' : 'Add') . ' Karyawan')

@section('content')

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="{{ route('user.list') }}">Karyawan</a></li>
          <li class="breadcrumb-item active">{{ (!empty($user)) ? ('Edit ' . $user->name) : 'Add' }}</li>
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
            <h3 class="card-title">{{ ( !empty($user) ) ? 'Edit Karyawan' : 'Add Karyawan' }}</h3>
          </div>
          <form method="post" action="{{ ( !empty($user) ) ? route('user.edit', ['id' => $user->id]) : route('user.add') }}">
            @csrf
            <div class="card-body">

              {{-- <div class="form-group">
                <p class="mb-1"><b>Image</b></p>
                <label for="photo" class="btn btn-success btn-sm" id="image-btn">Pilih</label>
                <div id="preview-image-container" class=" @if( empty($user) ) d-none @endif ">
                  @if ( !empty($user) && $user->image )
                  <img src="{{ $user->image }}" alt="{{ $user->name }}" id="preview-image" class="w-100">
                  @else
                  <img src="" alt="" id="preview-image" class="w-100">
                  @endif
                  
                </div>
                <input type="file" accept="image/png, image/jpeg" name="image" class="d-none" id="photo">
                @foreach($errors->get('photo') as $msg)
                  <span class="text-danger text-small">{{ $msg }}</span>
                @endforeach
              </div> --}}
              
              <div class="form-group">
                <label for="name" class="mb-0">Nama Lengkap</label>
                <input type="text" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" id="name" name="name" placeholder="Name" required value="{{ ( !empty($user) ) ? $user->name : old('name') }}">
                @foreach($errors->get('name') as $msg)
                  <span class="text-danger text-small">{{ $msg }}</span>
                @endforeach
              </div>

              <div class="form-group">
                <label for="username" class="mb-0">Username</label>
                <input type="text" class="form-control {{ $errors->has('username') ? 'is-invalid' : '' }}" id="username" name="username" placeholder="Username" required value="{{ ( !empty($user) ) ? $user->username : old('username') }}">
                @foreach($errors->get('username') as $msg)
                  <span class="text-danger text-small">{{ $msg }}</span>
                @endforeach
              </div>

              <div class="form-group">
                <label for="division_id" class="mb-0">Bagian</label>
                <select name="division_id" id="division_id" class="form-control">
                    <option disabled selected hidden value="">Pilih Bagian...</option>
                    @foreach ($divisions as $item)
                    <option value="{{ $item->id }}" @if ( !empty($user) AND $user->division_id == $item->id ) selected @else @if( old('division_id', '') == $item->id ) selected @endif @endif >{{ $item->name }}</option>
                    @endforeach
                </select>
                {{-- <input type="text" class="form-control {{ $errors->has('username') ? 'is-invalid' : '' }}" id="username" name="username" placeholder="Name" required value="{{ ( !empty($user) ) ? $user->username : old('username') }}"> --}}
                @foreach($errors->get('division_id') as $msg)
                  <span class="text-danger text-small">{{ $msg }}</span>
                @endforeach
              </div>

              <div class="form-group">
                <label for="password" class="mb-0">Password</label>
                <input type="password" class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}" id="password" name="password" placeholder="Isi untuk @if( !empty($user) ) mengganti @else menambah @endif password" value="">
                @if ( !empty($user) )
                <span class="text-small">Apabila tidak diisi, maka password tidak akan diganti</span>    
                @else
                <span class="text-small">Apabila tidak diisi, maka password menggunakan password default: <b>password</b></span>
                @endif
                
                @foreach($errors->get('password') as $msg)
                  <span class="text-danger text-small">{{ $msg }}</span>
                @endforeach
              </div>

            </div>
            <!-- /.card-body -->

            <div class="card-footer">
              <button type="submit" class="btn btn-success">{{ ( !empty($user) ) ? 'Edit' : 'Add' }}</button>
            </div>
          </form>
        </div>

      </div>

    </div>

  </div>
</section>

@endsection

@push('script')
{{-- <script>
const inputImage  = document.querySelector('#photo')
const previewImageContainer   = document.querySelector('#preview-image-container')
const previewImage  = document.querySelector('#preview-image')
const btnImage = document.querySelector('#image-btn')

if( Boolean(inputImage) ) {
  inputImage.addEventListener('change', ev => {
    const [file] = inputImage.files

    if( file ) {
      previewImageContainer.classList.remove('d-none')
      previewImage.src  = URL.createObjectURL(file)

      btnImage.innerHTML = 'Ganti'
    }
  })
}

</script> --}}
@endpush