<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationCenterController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('notification_deliveries')->where('user_id', $request->user()->id)->latest()->paginate(30)]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('notification_preferences')->where('user_id', $request->user()->id)->orderBy('event')->get()]);
    }

    public function updatePreference(Request $request): JsonResponse
    {
        $data = $request->validate(['event' => ['required', 'string', 'max:80'], 'channel' => ['required', 'in:internal,email,sms,whatsapp'], 'enabled' => ['required', 'boolean']]);
        DB::table('notification_preferences')->updateOrInsert(['user_id' => $request->user()->id, 'event' => $data['event'], 'channel' => $data['channel']], ['id' => (string) Str::uuid(), 'enabled' => $data['enabled'], 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Préférence enregistrée.']);
    }

        public function markAsRead(Request $request, string $notification): JsonResponse
        {
            $updated = DB::table('notifications')
                ->where('id', $notification)
                ->where('notifiable_type', 'App\\Models\\User')
                ->where('notifiable_id', $request->user()->id)
                ->whereNull('read_at')
                ->update(['read_at' => now(), 'updated_at' => now()]);

            abort_unless($updated === 1, 404);
            return response()->json(['message' => 'Notification marquée comme lue.']);
        }

        public function markAllAsRead(Request $request): JsonResponse
        {
            DB::table('notifications')
                ->where('notifiable_type', 'App\\Models\\User')
                ->where('notifiable_id', $request->user()->id)
                ->whereNull('read_at')
                ->update(['read_at' => now(), 'updated_at' => now()]);

            return response()->json(['message' => 'Notifications marquées comme lues.']);
        }
}
