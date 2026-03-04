<?php

namespace Modules\Flowmaker\Models;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;
use Carbon\Carbon;

class FlowExecution extends Model
{
    protected $table = 'flow_executions';

    protected $fillable = [
        'flow_id',
        'contact_id',
        'node_id',
        'executed_at'
    ];

    protected $dates = [
        'executed_at',
        'created_at',
        'updated_at'
    ];

    // Define any custom methods or scopes here
    protected static function booted(){
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model){
           $company_id = session('company_id', null);
            if($company_id){
                // Note: We might need to get company_id from the flow or contact
                // For now, we'll use the session value
            }
        });
    }

    /**
     * Relationship to Flow
     */
    public function flow()
    {
        return $this->belongsTo(Flow::class);
    }

    /**
     * Relationship to Contact
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Log a flow execution
     */
    public static function logExecution($flowId, $contactId, $nodeId = null)
    {
        return self::create([
            'flow_id' => $flowId,
            'contact_id' => $contactId,
            'node_id' => $nodeId,
            'executed_at' => now()
        ]);
    }

    /**
     * Count executions for a contact within a specific period
     */
    public static function countExecutions($flowId, $contactId, $period = 'all_time', $nodeId = null)
    {
        $query = self::where('flow_id', $flowId)
                    ->where('contact_id', $contactId);

        if ($nodeId) {
            $query->where('node_id', $nodeId);
        }

        if ($period === 'last_30_days') {
            $query->where('executed_at', '>=', Carbon::now()->subDays(30));
        }

        return $query->count();
    }

    /**
     * Get all executions for a contact and flow
     */
    public static function getExecutions($flowId, $contactId, $period = 'all_time', $nodeId = null)
    {
        $query = self::where('flow_id', $flowId)
                    ->where('contact_id', $contactId);

        if ($nodeId) {
            $query->where('node_id', $nodeId);
        }

        if ($period === 'last_30_days') {
            $query->where('executed_at', '>=', Carbon::now()->subDays(30));
        }

        return $query->orderBy('executed_at', 'desc')->get();
    }
}