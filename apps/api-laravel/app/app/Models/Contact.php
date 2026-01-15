<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'wa_id',
        'phone',
        'display_name',
        'avatar_url',
    ];

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }
}
