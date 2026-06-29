<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSubscription extends Model
{
    protected $table = 'project_subscription';

    protected $fillable = [
        'package_id',
        'status',
        'started_at',
        'expired_at',
    ];

    protected $casts = [
        'started_at' => 'date',
        'expired_at' => 'date',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
