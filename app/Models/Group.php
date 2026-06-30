<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'created_by',
    ];

    /**
     * The members that belong to the group.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The user who created the group.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The messages in this group.
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'group_id');
    }
}
