<?php

namespace App\Http\Livewire;

use App\Models\Bon;
use App\Models\Keterangan;
use App\Models\Libur;
use App\Models\Pegawai;
use App\Models\Pemasukan;
use Livewire\Component;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;



class Laporanpemasukan extends Component
{
    public $bulan;
    public $tahun;
    public $pegawai_id;
    public $keterangan_id;
    public $nama;
    public $keterangan;
    public $jumlah;
    public $total;
    public $bln;
    public $bon;
    public $libur;
    public $potongan;
    public $gaji;
    public $totalgaji;
    public $totalbonus;

    public function render()
    {

        $data = [
            'ket' => Keterangan::where('jenis', 'pemasukan')->get(),
            'pegawai' => Pegawai::get(),
            'now' => date('Y'),
        ];
        if (!empty($this->keterangan_id)) {

            $cari = Keterangan::find($this->keterangan_id);
            $jml = Pemasukan::whereMonth('tanggal', $this->bulan)->whereYear('tanggal', $this->tahun)->where('pegawai_id', $this->pegawai_id)->where('keterangan_id', $this->keterangan_id)->sum('jumlah');
            $tot = Pemasukan::whereMonth('tanggal', $this->bulan)->whereYear('tanggal', $this->tahun)->where('pegawai_id', $this->pegawai_id)->where('keterangan_id', $this->keterangan_id)->sum('total');
            $bn = Bon::whereMonth('tanggal', $this->bulan)->whereYear('tanggal', $this->tahun)->where('pegawai_id', $this->pegawai_id)->sum('jumlah');
            $lbr = Libur::whereMonth('tanggal', $this->bulan)->whereYear('tanggal', $this->tahun)->where('pegawai_id', $this->pegawai_id)->sum('jumlah');
            $peg = Pegawai::find($this->pegawai_id);
            $this->nama = $peg->nama;
            $this->bln = date('M', strtotime($this->tahun . '-' . $this->bulan . '-01'));
            $this->keterangan = $cari->namaket;
            $this->jumlah = $jml;
            $this->total = $tot;
            $this->libur = $lbr;
            $this->bon = $bn;
            if ($this->jumlah > 299) {
                $bonus = $this->jumlah - 300;
                $this->totalbonus = ($bonus * 2000) + 50000;
            }
            else{
                $this->totalbonus = 0;
            }
            if ($this->libur > 4) {
                $perlibur = 50000;
                $jumlahlibur = intval($this->libur) - 4;
                $this->potongan = $jumlahlibur * $perlibur;
                $this->totalgaji = $this->gaji + $this->totalbonus - $this->potongan - $this->bon;
            } else {
                $this->potongan = 0;
                $this->totalgaji = $this->gaji + $this->totalbonus - $this->potongan - $this->bon;
            }
        }
        return view('livewire.laporan.laporanpemasukan', $data);
    }
    public function print()
    {
        //request data
        $bulan = date('M', strtotime($this->tahun . '-' . $this->bulan . '-10'));
        // $totalpotong = Transaksi::whereMonth('tanggal', $request->month)->whereYear('tanggal', $request->tahun)->where('nama', $nama)->where('keterangan', $request->ket)->get()->sum('jumlah');

        /* Open file */
        $tmpdir = sys_get_temp_dir();
        $file =  tempnam($tmpdir, 'cetak');

        /* Do some printing */
        $connector = new FilePrintConnector($file);
        $printer = new Printer($connector);

        /* Print Logo */

        $img = EscposImage::load('./images/logoputih.png');
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->bitImageColumnFormat($img);
        // $printer->feed();
        /* Name of shop */
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Fiter Barber\n");
        $printer->selectPrintMode();
        $printer->text("Ngampel Kulon - Ngampel\n");
        $printer->text("Karangayu - Cepiring\n");
        // $printer->text($nama . "\n");
        $printer->feed();

        /* Title of receipt */
        $printer->setEmphasis(true);
        $printer->text("FITER BARBER INVOICE\n");
        $printer->setEmphasis(false);

        /* Information for the receipt */
        $items = array(
            new item("Nama", $this->nama),
            new item("Bulan", $bulan . " " . $this->tahun),
            new item("Libur", $this->libur . ' X'),
            new item("Total Potong", $this->jumlah . ' Kepala'),
            new item("Gaji", 'Rp. ' . number_format($this->gaji, 0, ".", ".") . ",-"),
            new item("Bonus", 'Rp. ' . number_format($this->totalbonus, 0, ".", ".") . ",-"),
            new item("Bon", 'Rp. ' . number_format($this->bon, 0, ".", ".") . ",-"),
            new item("Pot. Libur", 'Rp. ' . number_format($this->potongan, 0, ".", ".") . ",-"),
            new item("Total Gaji", 'Rp. ' . number_format($this->totalgaji, 0, ".", ".") . ",-"),
        );
        $date = gmdate('d M Y');

        /* Items */
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(true);
        $printer->text(new item('', '')); //Rp
        $printer->setEmphasis(false);
        foreach ($items as $item) {
            $printer->text($item);
        }
        $printer->feed();

        /* Footer */
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Fiter Barber\n");
        // $printer->text("Tampan dan Berani\n");
        $printer->feed();
        $printer->text($date . "\n");

        /* Cut the receipt and open the cash drawer */
        $printer->cut();
        $printer->pulse();

        $printer->close();

        /* Copy it over to the printer */
        copy($file, "//localhost/Gudang2");
        // copy($file, "//localhost/EPSONTU");
        unlink($file);
        // return redirect('/laporan');
    }

    //     public function print2(){
    //         var config = qz.configs.create("Printer Name");

    //         var data = [
    //     '\x1B' + '\x69' + '\x61' + '\x00' + '\x1B' + '\x40', // set printer to ESC/P mode and clear memory buffer
    //     '\x1B' + '\x69' + '\x4C' + '\x01', // set landscape mode
    //     '\x1B' + '\x55' + '\x02', '\x1B' + '\x33' + '\x0F', // set margin (02) and line feed (0F) values
    //     '\x1B' + '\x6B' + '\x0B' + '\x1B' + '\x58' + '\x00' + '\x3A' + '\x00', // set font and font size 
    //     'Printed by ', // "Printed by "
    //     'QZ-Tray', // "QZ-Tray"
    //     '\x0A' +'\x0A',// line feed 2 times
    //     '\x1B' + '\x69' + '\x74' + '\x30', // set to code39 barcode
    //     '\x72' + '\x31', // characters below barcode
    //     '\x65' + '\x30' + '\x68' + '\x65' + '\x00' + '\x77' +'\x34' + '\x7A' + '\x32', // parentheses y/n, height, width of barcode, 2:1 ratio wide to narrow bars
    //     '\x42' + '1234567890' + '\x5C', // begin barcode data, data, end barcode data
    //     '\x0A' + '\x0A', // line feed 2x
    //     '\x0C' // <--- Tells the printer to print 
    // ];

    //     qz.print(config, data).catch(function(e) { console.error(e); });

    //     }
}
