<?php

namespace App\Console\Commands;

use App\Http\Services\FirebaseService;
use App\Models\Notification;
use App\Models\FcmToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled push notifications';

    protected $firebaseService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing scheduled notifications...');

        // Proses notifikasi dalam chunk untuk efisiensi memori
        Notification::where('scheduled_at', '<=', now())
            ->where('status', 'pending')
            ->chunk(50, function ($notifications) {
                foreach ($notifications as $notification) {
                    try {
                        if ($notification->is_multicast) {
                            // Mengambil semua token FCM yang terdaftar
                            $tokens = FcmToken::pluck('fcm_token')->toArray();
                            
                            if (empty($tokens)) {
                                Log::warning("No FCM tokens found for multicast.");
                                continue;
                            }

                            $results = $this->firebaseService->sendPushNotificationToMultipleDevices(
                                $tokens,
                                $notification->title,
                                $notification->body,
                                $notification->image
                            );

                            // FirebaseService::sendPushNotificationToMultipleDevices di project ini
                            // mengembalikan array berisi info batch, bukan object report.
                            $totalSuccess = array_sum(array_column($results, 'success'));
                            $totalFailures = array_sum(array_column($results, 'failures'));

                            Log::info("Multicast sent. Success: {$totalSuccess}, Failures: {$totalFailures}");

                        } else {
                            if (!$notification->fcm_token) {
                                Log::warning("No FCM token for notification ID {$notification->id}");
                                continue;
                            }

                            $success = $this->firebaseService->sendPushNotification(
                                $notification->fcm_token,
                                $notification->title,
                                $notification->body,
                                $notification->image
                            );

                            if (!$success) {
                                Log::error("Failed to send notification to token: {$notification->fcm_token}");
                                continue;
                            }
                        }
                        
                        Log::info("Berhasil mengirim notifikasi ID: {$notification->id}");
                        // Update status setelah sukses kirim
                        $notification->update(['status' => 'sent']);

                    } catch (\Throwable $e) {
                        Log::error("Error sending notification ID {$notification->id}: " . $e->getMessage());
                        // Jangan throw exception agar chunk lain tetap lanjut
                        continue;
                    }
                }
            });

        $this->info('Scheduled notifications processed.');
    }
}
