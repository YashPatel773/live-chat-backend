<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Http\Request;

class MessageReactionController extends Controller
{
    public function toggleReaction(Request $request, $messageId)
    {
        $request->validate([
            'reaction' => 'required|string'
        ]);

        $userId = auth()->id();
        $message = Message::findOrFail($messageId);

        // Check if user is part of the message conversation
        if ($message->group_id) {
            if (!$message->group->members->contains($userId)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } else {
            if ($message->sender_id !== $userId && $message->receiver_id !== $userId) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        // Find or create reaction
        $existing = MessageReaction::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->reaction === $request->reaction) {
                $existing->delete();
            } else {
                $existing->update([
                    'reaction' => $request->reaction
                ]);
            }
        } else {
            // Create new
            MessageReaction::create([
                'message_id' => $messageId,
                'user_id' => $userId,
                'reaction' => $request->reaction
            ]);
        }

        // Return updated list of reactions
        $reactions = MessageReaction::where('message_id', $messageId)
            ->with('user:id,name')
            ->get();

        return response()->json([
            'success' => true,
            'reactions' => $reactions
        ]);
    }
}
