<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the signatureType that owns the Signature
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function signatureType(): BelongsTo
    {
        return $this->belongsTo(SignatureType::class, );
    }
}
