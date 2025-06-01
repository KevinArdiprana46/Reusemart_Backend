<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


function sendFCMWithJWT($fcmToken, $title, $body)
{
    try {
        $jsonPath = base_path(env('FIREBASE_CREDENTIALS'));
        $client = new Google_Client();
        $client->setAuthConfig($jsonPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();

        $accessToken = $client->getAccessToken()['access_token'];

        Log::info('✅ Firebase Access Token:', ['token' => $accessToken]);

        $jsonKey = json_decode(file_get_contents($jsonPath), true);
        $projectId = $jsonKey['project_id'];

        $response = Http::withToken($accessToken)->post(
            "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
            [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'android' => [
                        'priority' => 'high',
                    ],
                ]
            ]
        );

        Log::info('✅ FCM response:', $response->json());

        return $response->json();
    } catch (\Exception $e) {
        Log::error('❌ FCM error: ' . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}
