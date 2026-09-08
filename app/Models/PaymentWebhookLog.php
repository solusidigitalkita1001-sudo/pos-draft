<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['order_id', 'transaction_status', 'signature_valid', 'raw_payload'])]
class PaymentWebhookLog extends Model
{
    /**
     * The model does not have an `updated_at` column — this log is
     * append-only.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'raw_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
