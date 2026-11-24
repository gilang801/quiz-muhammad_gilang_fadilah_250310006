<?php
class Database {
    public static function koneksi() {
        return new PDO("mysql:host=localhost;dbname=rental_ps", "root", "");
    }
}

abstract class Unit {
    protected $id;
    protected $nama;
    private $tarif;

    public function __construct($id, $nama, $tarif) {
        $this->id = $id;
        $this->nama = $nama;
        $this->tarif = $tarif;
    }

    public function getId() { return $this->id; }
    public function getNama() { return $this->nama; }
    public function getTarif() { return $this->tarif; }

    abstract public function hitungBiaya($jam);
    public static function semua() {
        $hasil = [];
        $data = Database::koneksi()->query("SELECT * FROM unit")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $d) {
            $hasil[] = ($d['tipe'] == 'PS5')
                ? new PS5($d['id'], $d['nama'], $d['tarif'])
                : new PS4($d['id'], $d['nama'], $d['tarif']);
        }
        return $hasil;
    }
}

class PS4 extends Unit {
    public function hitungBiaya($jam) {
        return $this->getTarif() * $jam;
    }
}

class PS5 extends Unit {
    public function hitungBiaya($jam) {
        $total = $this->getTarif() * $jam;
        if ($jam >= 3) {
            $total = $total * 0.9;   
        }
        return $total;
    }
}

class Sewa {
    public function buat($unit, $penyewa, $durasi) {
        $total = $unit->hitungBiaya($durasi);
        $stmt = Database::koneksi()->prepare(
            "INSERT INTO sewa (unit_id, penyewa, durasi, total) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$unit->getId(), $penyewa, $durasi, $total]);
    }

    public static function semua() {
        $sql = "SELECT sewa.penyewa, unit.nama, sewa.durasi, sewa.total
                FROM sewa JOIN unit ON sewa.unit_id = unit.id";
        return Database::koneksi()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function totalPendapatan() {
        return Database::koneksi()->query("SELECT SUM(total) FROM sewa")->fetchColumn();
    }
}


$daftarUnit = Unit::semua();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($daftarUnit as $u) {
        if ($u->getId() == $_POST['unit_id']) {
            (new Sewa())->buat($u, $_POST['penyewa'], (int) $_POST['durasi']);
        }
    }
    header("Location: index.php");   
    exit;
}
?>
<h2>Rental PS</h2>

<form method="POST">
    Nama: <input type="text" name="penyewa" required><br><br>
    Unit:
    <select name="unit_id">
        <?php foreach ($daftarUnit as $u): ?>
            <option value="<?= $u->getId() ?>"><?= $u->getNama() ?> (Rp<?= $u->getTarif() ?>/jam)</option>
        <?php endforeach; ?>
    </select><br><br>
    Durasi (jam): <input type="number" name="durasi" min="1" value="1"><br><br>
    <button type="submit">Simpan</button>
</form>

<h3>Daftar Sewa</h3>
<table border="1" cellpadding="6">
    <tr><th>Penyewa</th><th>Unit</th><th>Durasi</th><th>Total</th></tr>
    <?php foreach (Sewa::semua() as $s): ?>
        <tr>
            <td><?= $s['penyewa'] ?></td>
            <td><?= $s['nama'] ?></td>
            <td><?= $s['durasi'] ?> jam</td>
            <td>Rp<?= $s['total'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<p>Total pendapatan: Rp<?= Sewa::totalPendapatan() ?? 0 ?></p>