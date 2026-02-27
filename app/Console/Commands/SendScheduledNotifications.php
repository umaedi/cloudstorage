<?php

namespace App\Console\Commands;

use App\Http\Services\FirebaseService;
use App\Models\Notification;
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
    // Note: tokenController is in constructor signature but not injected. Assuming it might be used via another way or we need to adjust based on your actual codebase.
    // For now, replicating the provided structure.
    protected $tokenController;

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
                            // TODO: Implement the logic to get tokens from TokenController or similar source
                            // as $this->tokenController is not injected in the constructor.
                            // Currently using a placeholder or assuming it's available. If TokenController is needed, 
                            // it should be injected via constructor or resolved from container.
                            $tokens = []; // Placeholder. Please adjust to fetch actual tokens.
                            //$tokens = $this->tokenController->getTokens(); 
                            
                            if (empty($tokens)) {
                                Log::warning("No FCM tokens found for multicast.");
                                continue;
                            }

                            $report = $this->firebaseService->sendPushNotificationToMultipleDevices(
                                $tokens,
                                $notification->title,
                                $notification->body,
                                $notification->image
                            );

                            if ($report->hasFailures()) {
                                foreach ($report->failures()->tokens() as $failedToken) {
                                    Log::warning("Failed to send to token: {$failedToken}");
                                }
                            }

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
                        
                        Log::info('Berhasil mengirim notifikasi');
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
