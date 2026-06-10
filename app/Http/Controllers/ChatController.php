<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{

    /**
     * Fetch all active users (excluding the current user)
     */
    public function getUsers()
    {
        // Get the currently logged-in user
        $currentUser = auth()->user();

        // Fetch all users from the database, excluding the current user, and load their profile images
        $users = User::where('id', '!=', $currentUser->id)
            ->select('id', 'name', 'email')
            ->get();

        return response()->json($users);
    }

    /**
     * Send a message to a specific user
     */
    public function sendMessage(Request $request)
    {
        // 1. Validate the incoming message data
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);

        // 2. Create the message in the database
        $message = Message::create([
            'sender_id' => auth()->id(),          // The ID of the logged-in user
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'is_seen' => false                  // Messages are initially unread
        ]);

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Fetch the chat history between two users
     */
    public function getMessages($userId)
    {
        $currentUser = auth()->id();

        // Retrieve messages between the current user and the specified user, ordered by time
        $messages = Message::with(['sender', 'receiver'])
            ->where(function ($query) use ($currentUser, $userId) {
                $query->where('sender_id', $currentUser)
                    ->where('receiver_id', $userId);
            })
            ->orWhere(function ($query) use ($currentUser, $userId) {
                $query->where('sender_id', $userId)
                    ->where('receiver_id', $currentUser);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }

    /**
     * Mark all messages from a user as seen
     */
   /**
 * Mark all unread messages from a specific sender as seen
 */
public function markAsSeen($senderId)
{
    $receiverId = auth()->id();

    // Find all unread messages sent to me by this specific user
    Message::where('sender_id', $senderId)
        ->where('receiver_id', $receiverId)
        ->where('is_seen', false)
        ->update(['is_seen' => true]);

    return response()->json([
        'success' => true,
        'message' => 'Conversation marked as read.'
    ]);
}
}
