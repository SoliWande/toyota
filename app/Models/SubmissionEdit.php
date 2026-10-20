<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class SubmissionEdit extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = ['changes' => 'array', 'edited_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Submission edit history is append-only.'));
        static::deleting(fn () => throw new LogicException('Submission edit history is append-only.'));
    }
}
