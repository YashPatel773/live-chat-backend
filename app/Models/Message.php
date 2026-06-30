<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    // This array tells Laravel exactly which columns are allowed to be modified or saved via code
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'group_id',
        'message',
        'is_seen',
        'type',       
        'file_path',  
        'file_name'
    ];

    /**
     * Relationship: A message belongs to a user (the sender)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Relationship: A message belongs to a user (the receiver)
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Relationship: A message can belong to a group
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
