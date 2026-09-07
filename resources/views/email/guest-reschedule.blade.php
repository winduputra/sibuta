<div style="margin: auto;">
    <div>
        <p>Salam, {{ $guest->name }}</p>
        <p>Permintaan pertemuan Anda pada tanggal {{ $guest->schedule }} diminta untuk dijadwalkan ulang pada tanggal {{ $guest->reschedule }} dengan alasan {{ $guest->reschedule_reason }}</p>
        <p>Mohon buat buat ulang janji temu sesuai tanggal di link ini {{ url('/') }} </p>
    </div>
</div>