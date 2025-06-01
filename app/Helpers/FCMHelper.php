<?php

use Illuminate\Support\Facades\Http;
new \Google_Client;

if (!function_exists('sendFCMWithJWT')) {
    function sendFCMWithJWT($fcmToken, $title, $body)
    {
        $jsonPath = base_path(env('FIREBASE_CREDENTIALS'));
        $jsonKey = json_decode(file_get_contents($jsonPath), true);


        $client = new Google_Client();
        $client->setAuthConfig($jsonKey);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $accessToken = $client->getAccessToken()['access_token'];

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

        return $response->json();
    }
}
