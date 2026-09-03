<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceStepAssignment extends Model
{
    use HasFactory;

    protected $guarded = [];


    /**
     * Get the instanceStep that owns the WorkflowInstanceStepAssignment
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function instanceStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstanceStep::class);
    }

     public function getCreatedAtAttribute($value)
    {
        if (!$value) {
            return null; // ou return '';
        }


        //     return Carbon::parse($value, 'Africa/Douala')
        // ->format('d-m-Y H:i');

        return \Carbon\Carbon::parse($value)->format("d-m-Y H:i");
    }

        protected function serializeDate(\DateTimeInterface $date)
    {
        return $date
            ->setTimezone(new \DateTimeZone('Africa/Douala'))
            ->format('Y-m-d\TH:i:s.uP');
    }
}
