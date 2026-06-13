<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_seen',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // This tells the JWT package to use the database 'id' column to identify the logged-in user
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }


    public function getJWTCustomClaims()
    {
        return [];
    }


    /** 
     * Friends 
     */
    public function friendsOfMine()
    {
        return $this->belongsToMany(User::class, 'friendships', 'sender_id', 'receiver_id')
            ->wherePivot('status', 'accepted');
    }

    /**
     * Friends where the current user received the request and accepted it
     */
    public function friendOf()
    {
        return $this->belongsToMany(User::class, 'friendships', 'receiver_id', 'sender_id')
            ->wherePivot('status', 'accepted');
    }

    /**
     * Helper attribute to merge both sides and get a complete list of friends for the sidebar
     */
    public function getFriendsAttribute()
    {
        return $this->friendsOfMine->merge($this->friendOf);
    }



    /**
     * Get the attributes that should be cast.
     *
     *  
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen' => 'datetime'
        ];
    }
}
