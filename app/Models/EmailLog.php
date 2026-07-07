<?php

namespace App\Models;

use App\Enums\EmailLogStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'recipient',
    'subject',
    'body',
    'status',
    'error_message',
    'reference_type',
    'reference_id',
    'sent_at',
])]
class EmailLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailLogStatus::class,
            'reference_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
