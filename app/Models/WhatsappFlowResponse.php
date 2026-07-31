<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappFlowResponse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_flow_responses';

    protected $fillable = [
        'company_id',
        'whatsapp_flow_id',
        'flow_id',
        'flow_node_id',
        'contact_id',
        'contact_phone',
        'contact_name',
        'flow_token',
        'abandonment_hours',
        'variable_prefix',
        'send_error',
        'responses',
        'status',
        'notes',
        'sent_at',
        'completed_at',
        'webhook_dispatched_at',
    ];

    protected $casts = [
        'responses' => 'array',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'webhook_dispatched_at' => 'datetime',
    ];

    /**
     * Get the WhatsApp Flow associated with this response.
     */
    public function whatsappFlow()
    {
        return $this->belongsTo(WhatsappFlow::class, 'whatsapp_flow_id');
    }

    /**
     * Get the automation flow associated with this response.
     */
    public function flow()
    {
        return $this->belongsTo(\Modules\Flowmaker\Models\Flow::class, 'flow_id');
    }

    /**
     * Get the company associated with this response.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with this response.
     */
    public function contact()
    {
        return $this->belongsTo(\Modules\Flowmaker\Models\Contact::class, 'contact_id');
    }

    /**
     * Scope to get completed responses.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get abandoned responses.
     */
    public function scopeAbandoned($query)
    {
        return $query->where('status', 'abandoned');
    }

    /**
     * Scope to get responses for a specific flow.
     */
    public function scopeForFlow($query, $flowId)
    {
        return $query->where('whatsapp_flow_id', $flowId);
    }

    /**
     * Scope to get responses for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Merge in-progress form answers without completing the response.
     *
     * @param  array<string, mixed>  $data
     */
    public function mergeResponses(array $data): void
    {
        if ($data === []) {
            return;
        }

        $existing = is_array($this->responses) ? $this->responses : [];

        $this->update([
            'responses' => array_merge($existing, $data),
        ]);
    }

    /**
     * Mark response as completed with data.
     */
    public function markCompleted(array $data)
    {
        $this->update([
            'responses' => $data,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark response as abandoned.
     */
    public function markAbandoned($reason = null)
    {
        $this->update([
            'status' => 'abandoned',
            'notes' => $reason,
        ]);
    }
}
