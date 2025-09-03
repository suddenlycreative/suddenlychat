<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CampaignContact extends Model
{
    protected $guard = ['id'];
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }


    public function statusBadge(): Attribute
    {
        return new Attribute(function () {
            $html = '';
            if ($this->status == Status::CAMPAIGN_MESSAGE_NOT_SENT) {
                $html = '<span class="custom--badge badge--secondary">Not Sent</span>';
            } elseif ($this->status == Status::CAMPAIGN_MESSAGE_IS_SENT) {
                $html = '<span class="custom--badge badge--primary">Sent</span>';
            } elseif ($this->status == Status::CAMPAIGN_MESSAGE_IS_FAILED) {
                $html = '<span class="custom--badge badge--danger">Failed</span>';
            } else {
                $html = '<span class="custom--badge badge--success">Success</span>';
            }

            return $html;
        });
    }
}
