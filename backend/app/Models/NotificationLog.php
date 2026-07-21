<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'channel', 'notification_type', 'recipient', 'subject', 'message',
        'status', 'provider_reference', 'error_message', 'idempotency_key',
        'notifiable_type', 'notifiable_id',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }
}
