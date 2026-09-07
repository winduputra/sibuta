@extends('layouts.front')

@push('style')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="{{ url('plugins/fontawesome-free/css/all.min.css') }}">
@endpush

@section('content')
<div class="container mt-5 mb-5">
    <div class="row justify-content-center">

        <div class="col-md-12 text-center text-white">
            <h1>ISI DATA TAMU</h1>
            <p>Silahkan lengkapi form di bawah ini sesuai keperluan anda.</p>
        </div>

        <div class="col-md-12">

            <div class="row no-guts">
                <div class="col-md-4 bg-primary d-flex justify-content-center align-items-center" style="border-top-left-radius: 32px; border-bottom-left-radius: 32px; flex-direction: column;">
                    <h1 class="text-white" style="font-size: 1rem">ISI BUKU TAMU</h1>
                    <div class="mb-3" style="width: 50%;">
                        <img src="https://i0.wp.com/satpolpp.ngawikab.go.id/wp-content/uploads/2023/09/png-clipart-logo-lpse-kota-pekanbaru-lpse-provinsi-riau-brand-logo-lpse-kota-pekanbaru.png?ssl=1" alt="" class="w-100">
                    </div>
                    <p class="text-white text-center" style="font-size: 12px;">Silahkan cek email anda untuk melihat informasi lebih lanjut</p>
                    <div class="d-flex justify-content-between" style="width: 50%">
                        <a href="#">
                            <i class="fab fa-facebook text-white"></i>
                        </a>

                        <a href="#">
                            <i class="fab fa-instagram text-white"></i>
                        </a>

                        <a href="#">
                            <i class="fab fa-twitter text-white"></i>
                        </a>

                        <a href="#">
                            <i class="fab fa-youtube text-white"></i>
                        </a>
                    </div>
                </div>

                <div class="col-md-8 p-md-5 bg-white" style="border-top-right-radius: 32px; border-bottom-right-radius: 32px;">
                    @if ( $errors->any() )
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                        <p class="mb-0">{!! $error !!}</p>
                        @endforeach
                    </div>
                    @endif

                    <form action="{{ route('guest.add') }}" id="guest-add-form" method="post" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group row">
                            <div class="col-md-6">
                                {{-- <label for="name" class="form-label">Nama</label> --}}
                                <input type="text" name="name" class="form-control" placeholder="Nama Lengkap" id="name" required value="{{ old('name', '') }}">
                            </div>
                            <div class="col-md-6">
                                {{-- <label for="name" class="form-label">Nama</label> --}}
                                <input type="text" name="phone" class="form-control" placeholder="No. HP" id="phone" required value="{{ old('phone', '') }}">
                            </div>
                            
                        </div>

                        <div class="form-group">
                            {{-- <label for="name" class="form-label">Nama</label> --}}
                            <input type="email" name="email" class="form-control" placeholder="Email" id="email" required value="{{ old('email', '') }}">
                        </div>

                        <div class="form-group">
                            <label for="guest_type" style="font-size: 12px;">Tipe Pengunjung</label>
                            <select name="guest_type" id="guest_type" class="form-control" required>
                                <option disabled selected hidden value="">Pilih Tipe...</option>
                                @foreach (['Penyedia', 'Non Penyedia'] as $item)
                                <option value="{{ $loop->iteration }}" @if ( old('guest_type', '') == $loop->iteration ) selected  @endif >{{ $item }}</option>    
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="division_id" style="font-size: 12px;">Bagian yang dituju</label>
                            <select name="division_id" id="division_id" class="form-control" required>
                                <option disabled selected hidden value="">Pilih Bagian...</option>
                                @foreach ($divisions as $item)
                                <option value="{{ $item->id }}" @if ( old('division_id', '') == $item->id ) selected @endif>{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group @if( old('user_id', '') == '' ) d-none @endif " id="employee-container">
                            <label for="user_id" style="font-size: 12px;">Nama Pegawai yang dituju</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option disabled selected hidden value="">Nama Pegawai...</option>
                                @foreach ($users as $item)
                                <option value="{{ $item->id }}" @if ( old('user_id', '') == $item->id ) selected @endif >{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ticket_type" style="font-size: 12px;">Tipe Tiket</label>
                            <select name="ticket_type" id="ticket_type" class="form-control" required>
                                <option disabled selected hidden value="">Pilih Tipe Ticket...</option>
                                @foreach (['Permintaan Layanan', 'Memenuhi Undangan', 'Kepentingan Lainnya'] as $item)
                                <option value="{{ $loop->iteration }}" @if ( old('ticket_type', '') == $loop->iteration ) selected  @endif >{{ $item }}</option>    
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            {{-- <label for="description" class="form-label">aksud</label> --}}
                            <textarea name="description" id="description" class="form-control" rows="4" placeholder="Maksud dan Tujuan" required>{{ old('description', '') }}</textarea>
                        </div>

                        <p id="permission" class="d-none bg-primary p-3 text-white">Mohon Izinkan Kamera kemudian Tunggu...</p>                        

                        <button id="ambilFotoButton" type="button" class="btn btn-primary btn-sm">Foto Selfie</button>
                        
                        <div id="selfie-container">
                        </div>

                        <br/>

                        <button id="foto" type="button" class="d-none btn btn-primary btn-sm">Ambil Foto</button>
                        <button id="resetFoto" type="button" class="d-none btn btn-primary btn-sm">Ulangi Foto</button>
                        <input type="file" id="selfie" name="selfie" accept="image/*" capture="user" style="display: none;" required>

                        <br/>

                        <br/>

                        <br/>

                        <button id="submit-btn" class="btn btn-success w-full">Kirim</button>

                    </form>
                </div>
            </div>

        </div>

    </div>
</div>

<div class="modal fade" id="waitModal" tabindex="-1" role="dialog" aria-labelledby="waitModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
        
        <div class="modal-body">
            Silahkan Tunggu...
        </div>
        
        </div>
    </div>
</div>

<div class="modal fade" id="surveyModal" tabindex="-1" role="dialog" aria-labelledby="surveyModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Perhatian</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
            </div>
        
            <div class="modal-body">
                <div class="alert alert-danger">
                    Anda belum mengisi <b>Survey Kepuasan Pengguna</b> dari Pertemuan Sebelumnya, Klik <a href="{{ route('survey.add') }}">di sini</a> untuk mengisi survey.
                </div>
            </div>
        
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
const employees = @json($users)

const employeeContainer = document.querySelector('#employee-container')
const divisionInput = document.querySelector('#division_id')
const userInput = document.querySelector('#user_id')

const guestAddForm = document.querySelector('#guest-add-form')
const submitBtn = document.querySelector('#submit-btn')

const waitModalEvent = new bootstrap.Modal(document.getElementById("waitModal"), {});
const surveyModalEvent = new bootstrap.Modal(document.getElementById("surveyModal"), {});

let selectableEmployees = []
let selectedDivisionId = 0

@if( $surveyModal )

document.onreadystatechange = function () {
  surveyModalEvent.show();
};

@endif

if( Boolean(guestAddForm) ) {
    guestAddForm.addEventListener('submit', ev => {
        submitBtn.setAttribute('disabled', 'disabled')
        submitBtn.innerHTML = 'Mohon tunggu... Data sedang dikirim'
        waitModalEvent.show()
    })
}

const updateSelect = () => {
    userInput.innerHTML = ''
    const optionPlaceholder = document.createElement('option')
    optionPlaceholder.setAttribute('disabled', 'disabled')
    optionPlaceholder.setAttribute('selected', 'selected')
    optionPlaceholder.setAttribute('hidden', 'hidden')
    optionPlaceholder.setAttribute('value', '')
    optionPlaceholder.innerHTML = 'Nama Pegawai...'

    userInput.appendChild(optionPlaceholder)

    selectableEmployees.forEach(item => {
        const optionElement = document.createElement('option')
        optionElement.setAttribute('value', item.id)
        optionElement.innerHTML = item.name
        userInput.appendChild(optionElement)
    })

    $('#user_id').select2();
}

if( Boolean(divisionInput) ) {
    divisionInput.addEventListener('change', ev => {
        employeeContainer.classList.remove('d-none')
        selectedDivisionId = divisionInput.value

        selectableEmployees = []
        employees.forEach(item => {
            console.log(selectedDivisionId, item)
            if( item.division_id == selectedDivisionId ) {
                selectableEmployees.push(item)
            }
        })

        updateSelect()
    })
}


</script>
@endpush

@push('script')
<script>
    const permission = document.querySelector('#permission')
    const fotoInput = document.getElementById('foto');
    const inputSelfie = document.querySelector('#selfie')
    const ambilFotoButton = document.getElementById('ambilFotoButton');
    const resetFotoButton = document.getElementById('resetFoto');
    const selfie = document.querySelector('#selfie-container')

    const takeSelfie = () => {
        ambilFotoButton.classList.add('d-none')
        permission.classList.remove('d-none')
        fotoInput.classList.remove('d-none')
        resetFotoButton.classList.add('d-none')

        navigator.mediaDevices.getUserMedia({ video: true })
            .then((stream) => {
                permission.classList.add('d-none')
                const video = document.createElement('video');
                video.setAttribute('style', 'width: 100%')
                video.srcObject = stream;
                video.play();
                selfie.innerHTML = ''
                selfie.appendChild(video);
                inputSelfie.stream = stream;
            })
            .catch((error) => {
                console.error('Error accessing camera:', error);
                ambilFotoButton.classList.remove('d-none')
                permission.classList.add('d-none')
                fotoInput.classList.add('d-none')
            })
            .finnaly(() => {
                
        });
    }

    ambilFotoButton.addEventListener('click', takeSelfie);
    resetFotoButton.addEventListener('click', takeSelfie);


    fotoInput.addEventListener('click', () => {
        const stream = inputSelfie.stream;

        fotoInput.classList.add('d-none')
        resetFotoButton.classList.remove('d-none')

        if (stream) {
            // Ambil gambar dari video dan hentikan streaming kamera
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            const video = document.querySelector('video');
            

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Hentikan streaming dan hapus elemen video
            const tracks = stream.getTracks();
            tracks.forEach((track) => track.stop());
            video.remove();

            // Ubah gambar menjadi Blob dan set sebagai value input file
            canvas.toBlob((blob) => {

                const list = new DataTransfer();

                const file = new File([blob], 'selfie.jpg', { type: 'image/jpeg' });
                list.items.add(file);
                let myFileList = list.files;
                inputSelfie.files = myFileList;
                
                // Optional: Anda dapat menampilkan gambar yang diambil
                const imgPreview = document.createElement('img');
                imgPreview.src = URL.createObjectURL(file);
                imgPreview.setAttribute('style', 'width: 100%')
                selfie.appendChild(imgPreview);
            }, 'image/jpeg');
        }
    });
</script>
@endpush

@push('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush