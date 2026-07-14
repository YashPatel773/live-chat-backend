<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    /**
     * Get all groups the authenticated user is a member of.
     */
    public function index()
    {
        $groups = auth()->user()->groups()
            ->with(['members', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($groups);
    }

    /**
     * Create a new group and associate selected members.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
        ]);

        $group = Group::create([
            'name' => $request->name,
            'created_by' => auth()->id(),
        ]);

        $memberIds = array_unique(array_merge($request->members, [auth()->id()]));

        $group->members()->attach($memberIds);

        $group->load(['members', 'creator']);

        return response()->json([
            'success' => true,
            'group' => $group,
        ], 201);
    }

    /**
     * Get all messages for a specific group.
     */
    public function getMessages($groupId)
    {
        $group = Group::findOrFail($groupId);

        // Security check:  Get MEssage
        if (!$group->members->contains(auth()->id())) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not a member of this group.',
            ], 403);
        }

        $cursor = request('cursor');
        $query = Message::where('group_id', $groupId)
            ->with(['sender', 'replyTo.sender', 'reactions.user:id,name']);

        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        $messages = $query->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        $messages = $messages->reverse()->values();

        $oldestMessage = $messages->first();
        $nextCursor = $oldestMessage ? $oldestMessage->id : null;
        $hasMore = false;
        
        if ($nextCursor) {
            $hasMore = Message::where('group_id', $groupId)
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

    /**
     * Add a member to a group.
     */
    public function addMember(Request $request, $groupId)
    {
        $request->validate([
            'member_id' => 'required|exists:users,id',
        ]);

        $group = Group::findOrFail($groupId);

        // Security check: Only the group creator (admin) can add members
        if ($group->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only the group creator can add members.',
            ], 403);
        }

        $memberId = $request->member_id;

        // Constraint: Only friends of the creator (admin) can be added
        $isFriend = DB::table('friendships')
            ->where('status', 'accepted')
            ->where(function ($query) use ($memberId) {
                $query->where(function ($q) use ($memberId) {
                    $q->where('sender_id', auth()->id())
                        ->where('receiver_id', $memberId);
                })->orWhere(function ($q) use ($memberId) {
                    $q->where('sender_id', $memberId)
                        ->where('receiver_id', auth()->id());
                });
            })
            ->exists();

        if (!$isFriend) {
            return response()->json([
                'success' => false,
                'message' => 'You can only add users who are your friends.',
            ], 422);
        }

        // Check if user is already a member
        if ($group->members()->where('user_id', $memberId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a member of the group.',
            ], 422);
        }

        // Add member
        $group->members()->attach($memberId);

        $group->load(['members', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully.',
            'group' => $group,
        ]);
    }

    /**
     * Remove a member from a group.
     */
    public function removeMember(Request $request, $groupId)
    {
        $request->validate([
            'member_id' => 'required|exists:users,id',
        ]);

        $group = Group::findOrFail($groupId);

        // Security check: Only the group creator (admin) can remove members
        if ($group->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only the group creator can remove members.',
            ], 403);
        }

        $memberId = $request->member_id;

        // Restriction: Cannot remove the group creator
        if ((int)$memberId === (int)$group->created_by) {
            return response()->json([
                'success' => false,
                'message' => 'The group creator cannot be removed from the group.',
            ], 422);
        }

        // Check if user is actually a member
        if (!$group->members()->where('user_id', $memberId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This user is not a member of the group.',
            ], 422);
        }

        // Remove member
        $group->members()->detach($memberId);

        $group->load(['members', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully.',
            'group' => $group,
        ]);
    }

    /*
     Leave Group function 
     */
    public function leaveGroup(Request $request, $groupId)
    { 
        $group = Group::findOrFail($groupId);
 
        $userId = auth()->user()->id;
 
        if (!$group->members()->where('user_id', $userId)->exists()) {
            return response()->json([
                'message' => 'You are not a member of this group.'
            ], 400);
        }

        if ((int)$userId === (int)$group->created_by) {
            return response()->json([
                'message' => 'The group creator cannot leave the group.'
            ], 400);
        }
 
        $group->members()->detach($userId);

        $group->load(['members', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'You have successfully left the group.',
            'group' => $group,
        ], 200);
    }
}
