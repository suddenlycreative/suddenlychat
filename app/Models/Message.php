<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\ApiQuery;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use ApiQuery;
    
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function chatbot()
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function statusBadge(): Attribute
    {
        return new Attribute(function () {
            $html = '';

            if ($this->status == Status::SENT) {
                $html = '<i class="la la-check text--secondary"></i>';
            } elseif ($this->status == Status::DELIVERED) {
                $html = '<i class="la la-check-double text--secondary"></i>';
            } elseif ($this->status == Status::READ) {
                $html = '<i class="la la-check-double text--success"></i>';
            } else {
                $html = '<i class="las la-redo-alt text--warning resender" data-id="' . e($this->id) . '"></i>';
            }

            return $html;
        });
    }
}
