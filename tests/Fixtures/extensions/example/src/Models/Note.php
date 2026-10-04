<?php

namespace Extensions\Example\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A note in the extension's own table, ext_example_notes. */
class Note extends Model
{
    protected $table = 'ext_example_notes';

    protected $fillable = ['body', 'user_id'];

    /** A core model, read-only for an extension: used here for the author's name. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
