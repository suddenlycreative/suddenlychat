<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{

    protected $casts = [
        'header' => 'array',
        'body' => 'array',
        'buttons' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(TemplateCategory::class, 'category_id');
    }

    public function whatsappAccount()
    {
        return $this->belongsTo(WhatsappAccount::class, 'whatsapp_account_id');
    }

    public function language()
    {
        return $this->belongsTo(TemplateLanguage::class, 'language_id');
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function verificationStatus(): Attribute
    {
        return Attribute::get(function () {
            return match ($this->status) {
                Status::TEMPLATE_PENDING => '<span class="custom--badge badge--primary">' . trans('Pending') . '</span>',
                Status::TEMPLATE_APPROVED => '<span class="custom--badge badge--success">' . trans('Approved') . '</span>',
                Status::TEMPLATE_REJECTED => '<span class="custom--badge badge--danger">' . trans('Rejected') . '</span>',
                Status::TEMPLATE_DISABLED => '<span class="custom--badge badge--warning">' . trans('Disabled') . '</span>',
                default => '',
            };
        });
    }

    // scopes
    public function scopeApproved($query)
    {
        return $query->where('status', Status::TEMPLATE_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', Status::TEMPLATE_PENDING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', Status::TEMPLATE_REJECTED);
    }

    public function scopeDisabled($query)
    {
        return $query->where('status', Status::TEMPLATE_DISABLED);
    }
}
