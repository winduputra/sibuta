<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Question;
use App\Models\Survey;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function form()
    {
        return view('report.form');
    }

    public function download()
    {
        $startDate = $this->request->start_at;
        $endDate = $this->request->end_at;

        if( !$startDate ) {
            $startDate = date('Y-m') . '-01 00:00:00';
        }

        if( !$endDate ) {
            $endDate = date('Y-m-t') . ' 23:59:59';
        }

        $guests = Guest::where('schedule', '>=', $startDate)->where('schedule', '<=', $endDate)->where('status', '=', 1)->orderBy('schedule', 'ASC')->get();


        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Tanggal');
        $sheet->setCellValue('B1', 'Nama');
        $sheet->setCellValue('C1', 'Email');
        $sheet->setCellValue('D1', 'No. HP');
        $sheet->setCellValue('E1', 'Tipe Pengunjung');
        $sheet->setCellValue('F1', 'Bagian Dituju');
        $sheet->setCellValue('G1', 'Pegawai Dituju');
        $sheet->setCellValue('H1', 'Tipe Tiket');
        $sheet->setCellValue('I1', 'Maksud Tujuan');
        $sheet->setCellValue('J1', 'Selfie');

        $row = 2;

        foreach ($guests as $key => $value) {

            $sheet->setCellValue('A' . $row, $value->schedule);
            $sheet->setCellValue('B' . $row, $value->name);
            $sheet->setCellValue('C' . $row, $value->email);
            $sheet->setCellValue('D' . $row, $value->phone);
            $sheet->setCellValue('E' . $row, $value->guest_type_string);
            $sheet->setCellValue('F' . $row, $value->division->name);
            $sheet->setCellValue('G' . $row, $value->user ? $value->user->name : $value->employee_name);
            $sheet->setCellValue('H' . $row, $value->ticket_type_string);
            $sheet->setCellValue('I' . $row, $value->description);
            $sheet->setCellValue('J' . $row, $value->selfie);

            $row++;
        }

        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setAutoSize(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="report.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        die();

    }

    public function reviewForm()
    {
        return view('report.form-review');
    }

    public function reviewDownload()
    {
        $startDate = $this->request->start_at;
        $endDate = $this->request->end_at;

        if( !$startDate ) {
            $startDate = date('Y-m') . '-01 00:00:00';
        }

        if( !$endDate ) {
            $endDate = date('Y-m-t') . ' 23:59:59';
        }

        $reviews = Survey::where('created_at', '>=', $startDate)->where('created_at', '<=', $endDate)->orderBy('created_at', 'ASC')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Nama');
        $sheet->setCellValue('B1', 'Instansi');
        $sheet->setCellValue('C1', 'Jabatan');
        $sheet->setCellValue('D1', 'No. HP');
        $sheet->setCellValue('E1', 'Email');
        $sheet->setCellValue('F1', 'Alamat Instansi');
        $sheet->setCellValue('G1', 'Tipe Pengunjung');
        $col = 'H';
        $questions = Question::all();
        $colIn = 'A';
        foreach ($questions as $key => $value) {
            $sheet->setCellValue($col . '1', $colIn);
            $col++; $colIn++;
        }

        $sheet->setCellValue($col . '1', 'Saran dan Masukan');

        $row = 2;

        foreach ($reviews as $key => $value) {

            $sheet->setCellValue('A' . $row, $value->name);
            $sheet->setCellValue('B' . $row, $value->organization);
            $sheet->setCellValue('C' . $row, $value->position);
            $sheet->setCellValue('D' . $row, $value->phone);
            $sheet->setCellValue('E' . $row, $value->email);
            $sheet->setCellValue('F' . $row, $value->address);
            $sheet->setCellValue('G' . $row, $value->guest_type_string);
            $answers = json_decode($value->survey_answers);
            $colIn = 'H';
            foreach ($answers as $answer) {
                $sheet->setCellValue($colIn . $row, $answer);
                $colIn++;
            }
            $sheet->setCellValue($col . $row, $value->review);
            $row++;
        }

        $row++; $row++; $row++;

        $colIn = 'A';
        foreach ($questions as $key => $value) {
            $sheet->setCellValue('A' . $row, $colIn);
            $sheet->setCellValue('B' . $row, $value->question);
            $sheet->mergeCells('B' . $row . ':G' . $row );
            $colIn++; $row++;
        }

        $colIn = 'A';
        while ($colIn <= $col) {
            $spreadsheet->getActiveSheet()->getColumnDimension($colIn)->setAutoSize(true);
            $colIn++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="report.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        die();
    }
}
