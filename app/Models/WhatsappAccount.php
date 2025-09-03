<?php

namespace App\Models;

use App\Traits\ApiQuery;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class WhatsappAccount extends Model
{
    use ApiQuery;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chatbots()
    {
        return $this->hasMany(Chatbot::class);
    }

    public function templates()
    {
        return $this->hasMany(Template::class, 'whatsapp_account_id');
    }

    public function welcomeMessage()
    {
        return $this->hasOne(WelcomeMessage::class);
    }

    public function verificationStatusBadge(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->code_verification_status == 'VERIFIED' ? '<span class="custom--badge badge--success">' . __('Verified') . '</span>' : '<span class="custom--badge badge--danger">' . __('Not Verified') . '</span>',
        );
    }
}
