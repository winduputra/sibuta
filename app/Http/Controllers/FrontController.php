<?php

namespace App\Http\Controllers;

use App\Mail\GuestPending;
use App\Mail\TestEmail;
use App\Models\Division;
use App\Models\Guest;
use App\Models\Question;
use App\Models\Survey;
use App\Models\User;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FrontController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function guestForm()
    {
        $divisions = Division::all();
        $users = User::all();

        $surveyModal = false;
        if( $this->request->must_survey ) {
            $surveyModal = true;
        }

        return view('guest.form', compact('divisions', 'users', 'surveyModal'));
    }

    public function guestCreate()
    {
        $this->request->validate([
            'name' => ['required'],
            'email' => ['required', 'email'],
            'phone' => ['required'],
            'guest_type' => ['required'],
            'division_id' => ['required'],
            'user_id' => ['required'],
            'ticket_type' => ['required'],
            'selfie' => ['required'],
            'description' => ['required'],
            // 'schedule' => ['required'],
        ]);

        $payloads = $this->request->only(['name', 'email', 'phone', 'guest_type', 'division_id', 'user_id', 'ticket_type', 'description', 'schedule']);
        if( !isset($payloads['schedule']) ) {
            $payloads['schedule'] = date('Y-m-d');
        }

        $user = User::find($payloads['user_id']);
        if( $user ) {
            $payloads['employee_name'] = $user->name;
        }

        $countSurvey = Survey::where('email', '=', $payloads['email'])->count();
        $pendingGuest = Guest::where('email', '=', $payloads['email'])->where('status', '=', 0)->count();
        $doneGuest = Guest::where('email', '=', $payloads['email'])->where('status', '=', 1)->count();

        if( $pendingGuest > 0 OR $countSurvey < $doneGuest ) {
            return redirect( route('guest.add', ['must_survey' => true]) )->withErrors('Anda belum mengisi <b>Survey Kepuasan Pengguna</b> dari Pertemuan Sebelumnya, Klik <a href="'.route('survey.add').'">di sini</a> untuk mengisi survey.');
        }

        if( $this->request->hasFile('selfie') ) {
            $file = $this->request->file('selfie');
            $now    = date("Y-m-d H:i:s");
            $extension  = strtolower($file->getClientOriginalExtension());
            $name   = strtotime($now) . '-' . mt_rand(1000,9999) . '.' . $extension;
            $selfie = $file->storeAs("image", $name);
            $payloads['selfie'] = url('image/' . $name);
        }



        $guest = Guest::create($payloads);

        $devices = Device::where('user_id', '=', $payloads['user_id'])->get();

        try {
            $fcmToken  = FcmService::getFCMAuthorizationToken(base_path('base.json'));
            foreach ($devices as $device) {
                $payloads   = [
                    'message' => [
                        'token' => $device->device_token,
                        'notification' => [
                            'body' => 'Terdapat Permintaan Temu Baru dari ' . $payloads['name'],
                            'title' => 'Permintaan Baru Diterima',
                            'image' => '',
                        ],
                        'data' => [
                            'title' => 'Permintaan Baru Diterima',
                            'message' => 'Terdapat Permintaan Temu Baru dari ' . $payloads['name'],
                            'image' => null,
                            'type' => 'guest',
                            'id' => ''. $guest->id,
                        ],
                        'apns'  => [
                            'payload' => [
                                'aps'   => [
                                    'mutable_content'   => true,
                                    'category'  => 'guest',
                                ]
                            ]
                        ]
                    ]
                ];
                $status = FCMService::sendNotification($fcmToken, $payloads);
            }
        } catch(\Exception $e) {
            // 
        }

        try {
            Mail::to($guest->email)->send(new GuestPending($guest));
        } catch (\Exception $e) {
            //throw $th;
        }

        // return view('guest.thankyou');
        return redirect('https://sibuta.bpbjprovlampung.id/terimakasih-tamu/');
    }

    public function surveyForm()
    {
        $questions = Question::all();
        return view('survey.form', compact('questions'));
    }

    public function surveyCreate()
    {
        $this->request->validate([
            'name' => ['required'],
            'email' => ['required'],
            'organization' => ['required'],
            'position' => ['required'],
            'address' => ['required'],
            'phone' => ['required'],
            'guest_type' => ['required'],
            'question_ids' => ['required'],
            'question_answers' => ['required'],
            'review' => ['required'],
        ]);
        
        $payloads = $this->request->only(['name', 'email', 'organization', 'position', 'address', 'phone', 'guest_type', 'question_ids', 'question_answers']);
        
        $payloads['survey_questions'] = json_encode($payloads['question_ids']);
        $payloads['survey_answers'] = json_encode($payloads['question_answers']);

        unset($payloads['question_ids']); unset($payloads['question_answers']);

        $survey = Survey::create($payloads);

        return redirect('https://sibuta.bpbjprovlampung.id/terimakasih-survey/');
    }

    public function chart($id)
    {
        $user = User::find($id);

        $guest = Guest::where('user_id', '=', $id)->where('schedule', 'like', date('Y-m') . '%')->orderBy('schedule', 'ASC')->get();

        $labels = [];
        // for($d=1; $d<=31; $d++)
        // {
        //     $time=mktime(12, 0, 0, date('m'), $d, date('Y'));
        //     if (date('m', $time)== date('m') )       
        //         array_push($labels, date('d', $time)); 
        // }
        $data = [];

        foreach ($guest as $value) {
            $key = array_search($value->schedule, $labels);
            if( $key === false ) {
                array_push($labels, $value->schedule);
                array_push($data, 1);
            } else {
                $data[$key] = $data[$key] + 1;
            }
        }

        return view('chart.show', compact('data', 'labels'));
    }

    public function download($id)
    {
        $user = User::find($id);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $data = [];

        $col = 2;

        foreach ($data as $key => $value) {
            // tanggal
            $sheet->setCellValue('B' . $col, $value['date']);

            // jummlah
            $sheet->setCellValue('B' . $col, $value['total']);

            $col++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="report.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        die();
    }

    public function testEmail()
    {
        $email = $this->request->email;

        try {
            Mail::to($email)->send(new TestEmail());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        return response()->json(['success' => true]);
    }

    public function testFcm($id)
    {
        $user = User::find($id);
        if( !$user ) {
            return response()->json(['success' => false, 'message' => 'Not Found']);
        }
        $devices = Device::where('user_id','=',$id)->get();
        try {
            $fcmToken  = FcmService::getFCMAuthorizationToken(base_path('base.json'));
            foreach ($devices as $device) {
                $payloads   = [
                    'message' => [
                        'token' => $device->device_token,
                        'notification' => [
                            'body' => 'Test FCM',
                            'title' => 'Test',
                            'image' => '',
                        ],
                        'data' => [
                            'title' => 'Test',
                            'message' => 'Test FCM',
                            'image' => null,
                            'type' => 'guest',
                            'id' => '1',
                        ],
                        'apns'  => [
                            'payload' => [
                                'aps'   => [
                                    'mutable_content'   => true,
                                    'category'  => 'guest',
                                ]
                            ]
                        ]
                    ]
                ];
                $status = FCMService::sendNotification($fcmToken, $payloads);
            }
        } catch(\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    public function privacy()
    {
        return view('front.privacy');
    }
}
