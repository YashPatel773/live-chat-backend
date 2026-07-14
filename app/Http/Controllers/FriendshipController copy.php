<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class FriendshipController extends Controller
{


    /*
     * get ALl request 
     */
    public function getRandomUsers()
    {
        $authId = auth()->id();
 
        $interactedUserIds = DB::table('friendships')
            ->whereIn('status', ['pending', 'accepted'])  
            ->where(function ($query) use ($authId) {
                $query->where('sender_id', $authId)
                    ->orWhere('receiver_id', $authId);
            })
            ->selectRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as user_id', [$authId])
            ->pluck('user_id')
            ->toArray();

        // Add yourself to the exclusion list
        $interactedUserIds[] = $authId;

        // Fetch only clean, new users
        $users = User::whereNotIn('id', $interactedUserIds)
            ->select('id', 'name', 'email')
            ->get();

        return response()->json($users);
    }

    /*
     * Send request
     */
    public function sendRequest(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id|not_in:' . auth()->id()
        ]);

        
        $friendship = DB::table('friendships')->updateOrInsert(
            ['sender_id' => auth()->id(), 'receiver_id' => $request->receiver_id],
            ['status' => 'pending', 'created_at' => now(), 'updated_at' => now()]
        );

        // NOTE: Right here is where you emit your Socket.io event: 
        // e.g., trigger real-time "friend-request-received" on the receiver's end.

        return response()->json([
            'success' => true,
            'message' => 'Friend request sent successfully.'
        ]);
    }

    /*
     * getPending request
     */

    public function getPendingRequests()
    {
        $requests = User::join('friendships', 'users.id', '=', 'friendships.sender_id')
            ->where('friendships.receiver_id', auth()->id())
            ->where('friendships.status', 'pending')
            ->select('users.id', 'users.name', 'users.email', 'friendships.id as friendship_id')
            ->get();

        return response()->json($requests);
    }

    /*
     * Accapt request 
     */

    public function acceptRequest(Request $request)
    {
        $request->validate([
            'sender_id' => 'required|exists:users,id'
        ]);

        DB::table('friendships')
            ->where('sender_id', $request->sender_id)
            ->where('receiver_id', auth()->id())
            ->update(['status' => 'accepted', 'updated_at' => now()]);

        // NOTE: Right here is where you emit your Socket.io event:
        // e.g., trigger "friend-request-accepted" to push HTML directly to both sidebars.

        return response()->json([
            'success' => true,
            'message' => 'Friend request accepted.'
        ]);
    }

    /*
    * Delete a friendship record
    */
    public function declineRequest(Request $request)
    {
        $request->validate([
            'sender_id' => 'required|exists:users,id'
        ]);

        // Deleting the record matches the clean approach so they can re-request later
        DB::table('friendships')
            ->where('sender_id', $request->sender_id)
            ->where('receiver_id', auth()->id())
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Friend request declined.'
        ]);
    }

    /*
     * Remove Friendship
     */
    public function removeFriend(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|exists:users,id'
        ]);

        $authId = auth()->id();
        $friendId = $request->friend_id;

        DB::table('friendships')
            ->where(function ($query) use ($authId, $friendId) {
                $query->where('sender_id', $authId)
                      ->where('receiver_id', $friendId);
            })
            ->orWhere(function ($query) use ($authId, $friendId) {
                $query->where('sender_id', $friendId)
                      ->where('receiver_id', $authId);
            })
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Friend removed successfully.'
        ]);
    }
}
