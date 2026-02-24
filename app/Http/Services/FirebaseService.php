<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use App\Models\FcmToken;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        // $serviceAccountPath = storage_path('app/siaptuba-firebase.json');
        $serviceAccountPath = storage_path('app/firebase-credential.json');
        $factory = (new Factory)->withServiceAccount($serviceAccountPath);
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send push notification to a single device
     *
     * @param string $token
     * @param string $title
     * @param string $body
     * @return bool
     */
    public function sendPushNotification(string $token, string $title, string $body, ?string $image = null, array $extraData = []): bool
    {
        try {
            $notification = [
                'title' => $title,
                'body' => $body,
            ];

            if (!empty($image)) {
                $notification['image'] = $image;
            }

            $dataPayload = array_merge([
                'image' => $image ?? '',
                'url'  => $extraData['url'] ?? '',
            ], $extraData);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($dataPayload);

            $this->messaging->send($message);
            return true;
        } catch (MessagingException | FirebaseException $e) {
            Log::error("Error sending notification to device: {$token}, Error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Send push notification to multiple devices
     *
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @return MulticastSendReport
     */
    public function sendPushNotificationToMultipleDevices(array $tokens, string $title, string $body, ?string $image = null, array $extraData = []): array
    {
        try {
            $notification = [
                'title' => $title,
                'body' => $body,
            ];

            if (!empty($image)) {
                $notification['image'] = $image;
            }

            // Data payload untuk client
            $dataPayload = array_merge([
                'image' => $image ?? '',
                'url'   => $extraData['url'] ?? '',
            ], $extraData);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($dataPayload);

            // Bagi token menjadi chunk per 500
            $chunks = array_chunk($tokens, 500);

            $allResults = [];

            foreach ($chunks as $index => $batchTokens) {
                $report = $this->messaging->sendMulticast($message, $batchTokens);

                Log::channel('daily')->info("FCM batch #{$index} sent. Success: {$report->successes()->count()}, Failures: {$report->failures()->count()}");

                // 🔥 Auto-hapus token invalid
                foreach ($report->failures()->getItems() as $failure) {

                    $error = $failure->error()->getMessage();
                    $failedToken = $failure->target()->value();

                    // Log setiap error secara detail
                    Log::channel('daily')->error("FCM Failure: Token={$failedToken} | Error={$error}");

                    // Jika error yang menyebabkan token invalid
                    if (
                        str_contains($error, 'not registered') ||
                        str_contains($error, 'Requested entity was not found') || // common error
                        str_contains($error, 'Invalid registration token') ||
                        str_contains($error, 'MismatchSenderId')
                    ) {
                        FcmToken::where('fcm_token', $failedToken)->delete();
                        Log::channel('daily')->warning("Deleted invalid FCM token: {$failedToken}");
                    }
                }


                // Simpan hasil batch
                $allResults[] = [
                    'batch'       => $index,
                    'success'     => $report->successes()->count(),
                    'failures'    => $report->failures()->count(),
                    'tokens_sent' => count($batchTokens),
                ];
            }
            return $allResults;

        } catch (MessagingException | FirebaseException $e) {
            Log::channel('daily')->error("Error sending multicast notification: {$e->getMessage()}");
            return [];
        }
    }
}
