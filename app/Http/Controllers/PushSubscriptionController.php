<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'content_encoding' => 'nullable|string',
            'user_agent' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $user->id,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => $data['user_agent'] ?? null,
            ]
        );

        return response()->json(['message' => 'Subscription saved.'], 200);
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
        ]);

        PushSubscription::where('endpoint', $data['endpoint'])->delete();

        return response()->json(['message' => 'Subscription removed.'], 200);
    }
}
