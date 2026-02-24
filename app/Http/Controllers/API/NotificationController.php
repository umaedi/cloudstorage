<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Services\FirebaseService;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    protected $firebaseService, $fcmController;

    public function __construct(FirebaseService $firebaseService, FCMController $fcmController)
    {
        $this->firebaseService = $firebaseService;
        $this->fcmController = $fcmController;
    }

    public function index(Request $request)
    {
        $userId = $request->input('user_id', '');
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $filter = $request->input('filter', "DESC");
        $skip = ($page - 1) * $limit;
        $all = $request->input('all', '');

        $notificationsQuery = Notification::query();

        if ($userId !== '') {
            $notificationsQuery->where('user_id', $userId);
            if ($all !== '') {
                $startDate = Carbon::now()->subDays(7);
                $endDate = Carbon::now();
                $notifications = $notificationsQuery->whereBetween('created_at', [$startDate, $endDate]);
                $message = 'Data notifikasi satu minggu terakhir';
            }else {
                $notifications = $notificationsQuery->whereDate('created_at', Carbon::today());
                $message = 'Data notifikasi hari ini';
            }

            $data = $notifications->where('read', 0)->latest()->get();
            $responseData = $data->map(function($item) {
              return [
                    'id'    => $item->id,
                    'title' => $item->title,
                    'body'   => $item->body,
                    'date'  => Carbon::parse($item->created_at)->translatedFormat('l, d F Y'),
                    'status'  => $item->status,
                    'read'  => $item->read,
                ];
            });
            return $this->success($message, $responseData);
        } else {
            $total = Notification::count();
            $data = Notification::skip($skip)->take($limit)->orderBy('created_at', $filter)->get();
            return $this->responseWithPaginate('Data semua notifikasi', $data, $page, $limit, $total);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'nullable|string',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'schedule' => 'sometimes|boolean',
            'is_multicast' => 'sometimes|boolean',
            'url'   => 'nullable|string',
            'image' => 'nullable|string',
        ], [
            'user_id.exists' => 'User tidak ditemukan',
            'title.required' => 'Title wajib diisi',
            'body.required' => 'Body wajib diisi',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $shouldSchedule = $request->boolean('schedule');
        $isMulticast = $request->boolean('is_multicast');

        $notificationData = [
            'user_id' => $request->input('user_id'),
            'fcm_token' => $request->input('fcm_token'),
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'image' => $request->input('image'),
            'url' => $request->input('url'),
            'scheduled_at' => $request->input('scheduled_at') ?? Carbon::now(),
            'is_multicast' => $isMulticast,
            // 'type'  => $request->input('type'),
            'screen' => 'BeritaScreen',
        ];


        Log::channel('daily')->info('Data notifikasi tersimpan',  $notificationData);
       

        try {
            $notification = Notification::create($notificationData);

            if ($shouldSchedule) {
                Log::channel('daily')->info('Notifikasi terjadwal berhasil dibuat',  $notification->toArray());
                return $this->success('Notifikasi terjadwal berhasil dibuat', $notification, 201);
            }

            if ($isMulticast) {
                $tokens = $this->fcmController->getTokens();
                $this->firebaseService->sendPushNotificationToMultipleDevices(
                    $tokens,
                    $notificationData['title'],
                    $notificationData['body'],
                    $notificationData['image'],
                    $notificationData
                );
            } else {
                $userToken = FcmToken::where('user_id', $request->input('user_id'))->first();
                $this->firebaseService->sendPushNotification(
                    $userToken->fcm_token,
                    $notificationData['title'],
                    $notificationData['body'],
                    $notificationData['image'],
                    $notificationData
                );
            }

            $notification->update(['status' => 'sent']);
            Log::channel('daily')->info('Notifikasi berhasil dikirim',  $notification->toArray());
            return $this->success('Notifikasi berhasil dikirim', $notification, 201);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::channel('daily')->error('Gagal membuat log notifikasi: ' . $e->getMessage());
            return $this->error('Gagal menyimpan data notifikasi' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            Log::channel('daily')->error('Gagal mengirim notifikasi: ' . $e->getMessage());
            return $this->error('Gagal mengirim notifikasi', 500);
        }
    }

    public function show(Request $request)
    {
        $userId = $request->input('user_id');
        $id = $request->input('id');

        if ($userId == null && $id == null) {
            return $this->error('Data tidak ditemukan', 404);
        } else {
            $notification = Notification::query()->where('user_id', $userId)->find($id);
            if (!$notification) {
                return $this->error('Notifikasi tidak ditemukan', 404);
            }

            $notification->read = 1;
            $notification->status = 'sent';
            $notification->save();

            return $this->success('Data notifikasi', $notification);
        }
    }


    public function count(Request $request)
    {
        $userId = $request->input('user_id', '');

        // filter by read status
        // 0 = unread, 1 = read
        $read = $request->input('read', null);

        $notifications = Notification::query()
        ->where('user_id', $userId)
        ->whereDate('created_at',Carbon::today());

        // filter notif yang belum dibaca
        if ($read !== null && $read == 0) {
            $notifications->where('read', 0);
            // filter notif yang sudah dibaca
        } elseif ($read !== null && $read == 1) {
            $notifications->where('read', 1);
        }

        $data  = $notifications->count();
        return $this->success('Jumlah notifikasi', $data);
    }
}
