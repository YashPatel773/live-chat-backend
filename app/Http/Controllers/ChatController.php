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
         
        $currentUser = auth()->user();
        $friends = $currentUser->friends->map(function ($friend) {
            return [
                'id' => $friend->id,
                'name' => $friend->name,
                'email' => $friend->email,
                'last_seen' => $friend->last_seen,
            ];
        });

        return response()->json($friends);
    }

    /**
     * Send a message to a specific user
     */
    public function sendMessage(Request $request)
    {
     
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);

        
        $message = Message::create([
            'sender_id' => auth()->id(),          
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'is_seen' => false                  
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
        
        $messages = Message::with(['sender', 'receiver'])
            ->where(function ($query) use ($currentUser, $userId) {
                $query->where('sender_id', $currentUser)
                    ->where('receiver_id', $userId)
                    ->where('deleted_by_sender', false);
            })
            ->orWhere(function ($query) use ($currentUser, $userId) {
                $query->where('sender_id', $userId)
                    ->where('receiver_id', $currentUser)
                    ->where('deleted_by_receiver', false);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }


    public function deleteMessage(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:me,everyone '
        ]);

        $message = Message::findOrFail($id);
        $authId = auth()->id();

        // Security Check: Ensure the logged-in user is actually part of this message conversation
        if ($message->sender_id !== $authId && $message->receiver_id !== $authId) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        if ($request->type === 'everyone') {
             
            // Only the sender should be allowed to unsend a message for everyone
            if ($message->sender_id !== $authId) {
                return response()->json(['message' => 'You can only delete your own messages for everyone.'], 403);
            }

             
            $message->delete();

            // NOTE: This is where you would emit a Socket.io event 'message_deleted_everyone' 
            // so it disappears from the receiver's React UI in real-time.

            return response()->json(['success' => true, 'message' => 'Message deleted for everyone.']);
        } else {

            if ($message->sender_id === $authId) {
                $message->update(['deleted_by_sender' => true]);
            } else {
                $message->update(['deleted_by_receiver' => true]);
            }

            return response()->json(['success' => true, 'message' => 'Message hidden for you.']);
        }
    }

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


    /*
     * LastSeen Offline user 
     */
    public function setUserOffline(Request $request)
    {
        $request->validate([
            "user_id" => 'required|exists:users,id'
        ]);

        $user = User::findOrFail($request->user_id);
        $user->update([
            'last_seen' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User last_seen timestamp updated successfully.',
            'user_id' => $user->id,
            'last_seen' => $user->last_seen->toIso8601String() // Formats cleanly for javascript consumption
        ], 200);
    }
}
