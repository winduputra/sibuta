@extends('layouts.front')

@section('content')
<div class="container mt-5 mb-5">
    <div class="row justify-content-center">

        <div class="col-md-12 text-center text-white">
            <h1>SURVEY KEPUASAN PENGGUNA</h1>
            <p>Silahkan lengkapi form di bawah ini sesuai yang anda rasakan.</p>
        </div>

        <div class="col-md-12">

            <div class="row no-guts">
                {{-- <div class="col-md-4 bg-primary" style="border-top-left-radius: 32px; border-bottom-left-radius: 32px;">
                    <h1></h1>
                </div> --}}

                <div class="col-md-12 p-md-5 bg-white" style="border-radius: 32px;">
                    @if ( $errors->any() )
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                        <p class="mb-0">{{ $error }}</p>
                        @endforeach
                    </div>
                    @endif

                    
                    <form action="" method="post" enctype="multipart/form-data" class="form">
                        @csrf

                        <div class="form-group row">
                            <div class="col-md-6">
                                <input type="text" name="name" class="form-control" placeholder="Nama Lengkap" id="name" value="{{ old('name', '') }}">
                            </div>
                            <div class="col-md-6">
                                <input type="email" name="email" class="form-control" placeholder="Email" id="email" value="{{ old('email', '') }}">
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-6">
                                <input type="text" name="organization" class="form-control" placeholder="Nama Instansi" id="organization" value="{{ old('organization', '') }}">
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="position" class="form-control" placeholder="Jabatan di Instansi" id="position" value="{{ old('position', '') }}">
                            </div>
                        </div>


                        <div class="form-group">
                            {{-- <label for="name" class="form-label">Nama</label> --}}
                            <input type="text" name="address" class="form-control" placeholder="Alamat Instansi" id="address" value="{{ old('address', '') }}">
                        </div>

                        <div class="form-group">
                            {{-- <label for="name" class="form-label">Nama</label> --}}
                            <input type="text" name="phone" class="form-control" placeholder="No. Telp" id="phone" value="{{ old('phone', '') }}">
                        </div>

                        <div class="form-group">
                            <label for="guest_type" style="font-size: 12px;">Tipe Pengunjung</label>
                            <select name="guest_type" id="guest_type" class="form-control">
                                <option disabled selected hidden value="">Pilih Tipe...</option>
                                @foreach (['Penyedia', 'Non Penyedia'] as $item)
                                <option value="{{ $loop->iteration }}" @if ( old('guest_type', '') == $loop->iteration ) selected  @endif >{{ $item }}</option>    
                                @endforeach
                            </select>
                        </div>

                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-center">Pertanyaan</th>
                                    <th class="text-center">Tidak Memuaskan</th>
                                    <th class="text-center">Kurang Memuaskan</th>
                                    <th class="text-center">Cukup Memuaskan</th>
                                    <th class="text-center">Memuaskan</th>
                                    <th class="text-center">Sangat Memuaskan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($questions as $item)
                                <tr>
                                    <td>
                                        {{ $item->question }}
                                        <input type="hidden" name="question_ids[{{$loop->index}}]" value="{{$item->id}}">
                                    </td>
                                    <td>
                                        <input type="radio" class="form-control" name="question_answers[{{$loop->index}}]" value="1">
                                    </td>
                                    <td>
                                        <input type="radio" class="form-control" name="question_answers[{{$loop->index}}]" value="2">
                                    </td>
                                    <td>
                                        <input type="radio" class="form-control" name="question_answers[{{$loop->index}}]" value="3">
                                    </td>
                                    <td>
                                        <input type="radio" class="form-control" name="question_answers[{{$loop->index}}]" value="4">
                                    </td>
                                    <td>
                                        <input type="radio" class="form-control" name="question_answers[{{$loop->index}}]" value="5">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="form-group">
                            {{-- <label for="name" class="form-label">Nama</label> --}}
                            <input type="text" name="review" class="form-control" placeholder="Saran dan Masukan" id="review" value="{{ old('review', '') }}">
                        </div>

                        <button class="btn btn-primary w-full">Kirim</button>

                    </form>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection