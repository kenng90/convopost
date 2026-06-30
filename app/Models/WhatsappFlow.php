<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappFlow extends MyModel
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'flow_json' => 'array',
        'meta_error' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Get the company that owns the flow.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the Meta credentials for this flow's WABA.
     */
    public function metaCredentials()
    {
        return $this->belongsTo(WhatsappMetaCredentials::class, 'waba_id', 'waba_id');
    }

    /**
     * Minimal columns for dropdowns (id + name only).
     */
    public function scopeForSelect($query)
    {
        return $query->select([
            'whatsapp_flows.id',
            'whatsapp_flows.name',
        ]);
    }

    /**
     * Lightweight query for index/list views (avoids loading large flow_json into sort buffer).
     */
    public function scopeForListing($query)
    {
        return $query->select([
            'whatsapp_flows.id',
            'whatsapp_flows.company_id',
            'whatsapp_flows.name',
            'whatsapp_flows.description',
            'whatsapp_flows.status',
            'whatsapp_flows.meta_flow_id',
            'whatsapp_flows.updated_at',
            'whatsapp_flows.created_at',
        ])->selectRaw(
            'COALESCE(JSON_LENGTH(JSON_EXTRACT(whatsapp_flows.flow_json, "$.screens")), 0) as screens_count'
        );
    }

    /**
     * Scope to only active flows.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to only draft flows.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to only flows published to Meta.
     */
    public function scopePublishedToMeta($query)
    {
        return $query->whereNotNull('meta_flow_id')->where('status', '!=', 'archived');
    }

    /**
     * Check if flow is published locally.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if flow is published to Meta.
     */
    public function isPublishedToMeta(): bool
    {
        return $this->meta_flow_id !== null;
    }

    /**
     * Get flow fields from JSON structure.
     */
    public function getFields(): array
    {
        return $this->flow_json['screens'][0]['fields'] ?? [];
    }

    /**
     * Get flow screens from JSON structure.
     */
    public function getScreens(): array
    {
        return $this->flow_json['screens'] ?? [];
    }

    public function responses()
    {
        return $this->hasMany(WhatsappFlowResponse::class, 'whatsapp_flow_id');
    }

    /**
     * Get flow settings.
     */
    public function getSettings(): array
    {
        return $this->flow_json['settings'] ?? [];
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'published' => 'Published Locally',
            'archived' => 'Archived',
            default => 'Unknown',
        };
    }

    /**
     * Get Meta publication status.
     */
    public function getMetaStatusLabel(): string
    {
        if (! $this->meta_flow_id) {
            return 'Not Published';
        }

        return 'Published to Meta';
    }
}
