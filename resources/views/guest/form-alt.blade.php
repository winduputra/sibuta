
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Tamu - Biro Pengadaan Barang dan Jasa Setda Prov. Lampung</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background-color: #f2f2f2;
	}
        

        form {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
	//    text-align: center;
        }

	h2, h3 {
	    text-align: center;
	    color: blue;
	}

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #333;
        }

        input, textarea, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #fff;
        }

        select {
            appearance: none;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }

        .d-none {
            display: none;
        }
    </style>
</head>
<body>

    <form action="{{ route('guest.add') }}" method="post" enctype="multipart/form-data">
        @csrf
        <h2>Selamat Datang Di Sistem Buku Tamu</h2>
        <h3>Biro Pengadaan Barang dan Jasa Setda Prov. Lampung</h3>

        @if ( $errors->any() )
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
            <p class="mb-0">{{ $error }}</p>
            @endforeach
        </div>
        @endif

        <label for="name">Nama Lengkap:</label>
        <input type="text" id="name" name="name" required value="{{ old('name', '') }}">

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required value="{{ old('email', '') }}">

        <label for="phone">No. Hp:</label>
        <input type="tel" id="phone" name="phone" required value="{{ old('phone', '') }}">

        <label for="guest_type">Tipe Pengunjung:</label>
        <select id="guest_type" name="guest_type" required>
            <option disabled selected hidden value="">Pilih Tipe...</option>
            @foreach (['Penyedia', 'PP/PPK', 'Lainnya'] as $item)
            <option value="{{ $loop->iteration }}" @if ( old('guest_type', '') == $loop->iteration ) selected  @endif >{{ $item }}</option>    
            @endforeach
        </select>

        <label for="description">Maksud dan Tujuan:</label>
        <textarea id="description" name="description" rows="4" required>{{ old('description', '') }}</textarea>

        <label for="division_id">Bagian yang Dituju:</label>
	    <select id="division_id" name="division_id" required>
	        <option disabled selected hidden value="">Pilih Bagian...</option>
            @foreach ($divisions as $item)
            <option value="{{ $item->id }}" @if ( old('division_id', '') == $item->id ) selected @endif>{{ $item->name }}</option>
            @endforeach
        </select>

        <label for="user_id">Nama Pegawai yang Dituju:</label>
        <select id="user_id" name="user_id" required>
            <option disabled selected hidden value="">Nama Pegawai...</option>
            @foreach ($users as $item)
            <option value="{{ $item->id }}" @if ( old('user_id', '') == $item->id ) selected @endif >{{ $item->name }}</option>
            @endforeach
        </select>

        <label for="schedule">Tanggal Keperluan:</label>
        <input type="date" id="schedule" name="schedule" required value="{{ old('schedule', '') }}">

        <label for="ticket_type">Tipe Tiket:</label>
        <select id="ticket_type" name="ticket_type" required>
            <option disabled selected hidden value="">Pilih Tipe Ticket...</option>
            @foreach (['Permintaan Layanan', 'Memenuhi Undangan', 'Kepentingan Lainnya'] as $item)
            <option value="{{ $loop->iteration }}" @if ( old('ticket_type', '') == $loop->iteration ) selected  @endif >{{ $item }}</option>    
            @endforeach
        </select>

        <p id="permission" class="d-none">Mohon Izinkan Kamera kemudian Tunggu...</p>

        <button id="ambilFotoButton" type="button">Ambil Foto Selfie</button>
        <br/>
        <br/>

        <div id="selfie-container">
        </div>
        <br/>

        <button id="foto" type="button" class="d-none">Ambil Foto</button>
        <button id="resetFoto" type="button" class="d-none">Ulangi Foto</button>
        <br/>
        <br/>

        <br/>
        <br/>

        <input type="file" id="selfie" name="selfie" accept="image/*" capture="user" style="display: none;">
        <button type="submit">Submit</button>
    </form>
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
</body>
</html>

