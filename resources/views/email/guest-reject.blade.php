<div style="margin: auto;">
    <div>
        <p>Salam, {{ $guest->name }}</p>
        <p>Permintaan pertemuan Anda pada tanggal {{ $guest->schedule }} ditolak dengan alasan {{ $guest->rejection_note }}</p>
    </div>
</div>