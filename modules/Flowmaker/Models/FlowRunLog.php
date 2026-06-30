<?php

namespace Modules\Flowmaker\Models;

use Illuminate\Database\Eloquent\Model;

class FlowRunLog extends Model
{
    protected $table = 'flow_run_logs';

    protected $fillable = [
        'flow_id',
        'contact_id',
        'node_id',
        'event',
        'detail',
    ];
}
