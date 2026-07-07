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
            'receiver_id' => 'required_without:group_id|nullable|exists:users,id',
            'group_id' => 'required_without:receiver_id|nullable|exists:groups,id',
            'message' => 'nullable|string',
            'file' => 'nullable|file|max:51200',
            'reply_to_id' => 'nullable|exists:messages,id'
        ]);

        $filePath = null;
        $fileName = null;
        $type = 'text';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $mime = $file->getMimeType();

            if (str_starts_with($mime, 'image/')) {
                $type = 'image';
            } elseif (str_starts_with($mime, 'video/')) {
                $type = 'video';
            } else {
                $type = 'document';
            }

            $filePath = $file->store('uploads', 'public');
        }

        $message = Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'group_id' => $request->group_id,
            'message' => $request->message,
            'type' => $type,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'is_seen' => false,
            'reply_to_id' => $request->reply_to_id,
        ]);

        $message->load('sender', 'replyTo.sender', 'reactions.user:id,name');

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
        $cursor = request('cursor');

        $query = Message::with(['sender', 'receiver', 'replyTo.sender', 'reactions.user:id,name'])
            ->where(function ($query) use ($currentUser, $userId) {
                $query->where(function ($q) use ($currentUser, $userId) {
                    $q->where('sender_id', $currentUser)
                        ->where('receiver_id', $userId)
                        ->where('deleted_by_sender', false);
                })
                ->orWhere(function ($q) use ($currentUser, $userId) {
                    $q->where('sender_id', $userId)
                        ->where('receiver_id', $currentUser)
                        ->where('deleted_by_receiver', false);
                });
            });

        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        // Fetch 20 oldest (based on cursor) messages sorted by id desc
        $messages = $query->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        // Reverse to show in chronological order (ascending)
        $messages = $messages->reverse()->values();

        $oldestMessage = $messages->first();
        $nextCursor = $oldestMessage ? $oldestMessage->id : null;
        $hasMore = false;
        
        if ($nextCursor) {
            $hasMore = Message::where(function ($query) use ($currentUser, $userId) {
                $query->where(function ($q) use ($currentUser, $userId) {
                    $q->where('sender_id', $currentUser)
                        ->where('receiver_id', $userId)
                        ->where('deleted_by_sender', false);
                })
                ->orWhere(function ($q) use ($currentUser, $userId) {
                    $q->where('sender_id', $userId)
                        ->where('receiver_id', $currentUser)
                        ->where('deleted_by_receiver', false);
                });
            })
            ->where('id', '<', $nextCursor)
            ->exists();
        }

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore
        ]);
    }


    public function deleteMessage(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:me,everyone'
        ]);

        $message = Message::findOrFail($id);
        $authId = auth()->id();
 
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

    /*
     * Delte whole Chat
     * 
     */
    public function clearChat($friendId)
    {
        $authId = auth()->id();

        // 1. If I am the sender, mark it as deleted_by_sender
        Message::where('sender_id', $authId)
            ->where('receiver_id', $friendId)
            ->update(['deleted_by_sender' => true]);

        // 2. If I am the receiver, mark it as deleted_by_receiver
        Message::where('sender_id', $friendId)
            ->where('receiver_id', $authId)
            ->update(['deleted_by_receiver' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Chat history cleared locally.'
        ]);
    }

    /**
     * Edit / Update an existing message sent by the authenticated user
     */
    public function updateMessage(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $message = Message::findOrFail($id);

        // Security check: Only the owner of the message can edit it
        if ($message->sender_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $message->update([
            'message' => $request->message,
            'is_edited' => true
        ]);

        // Eager load relations so it has reactions, sender, receiver, replyTo, etc.
        $message->load(['sender', 'receiver', 'replyTo.sender', 'reactions.user:id,name']);

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }
}
