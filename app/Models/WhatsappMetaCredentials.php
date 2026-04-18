<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Encryption\Encrypter;

class WhatsappMetaCredentials extends MyModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'whatsapp_meta_credentials';
    protected $guarded = [];

    protected $casts = [
        'credentials_data' => 'array',
        'expires_at' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
    ];

    /**
     * Get the company that owns these credentials.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all flows using this WABA.
     */
    public function flows()
    {
        return $this->hasMany(WhatsappFlow::class, 'waba_id', 'waba_id');
    }

    /**
     * Check if credentials are active and valid.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if token has expired.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the decrypted access token.
     */
    public function getAccessToken(): string
    {
        return decrypt($this->access_token);
    }

    /**
     * Set the access token (will be encrypted).
     */
    public function setAccessToken(string $token): void
    {
        $this->access_token = encrypt($token);
    }

    /**
     * Mark credentials as verified.
     */
    public function markAsVerified(): void
    {
        $this->update(['last_verified_at' => now()]);
    }

    /**
     * Scope to only active credentials.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to credentials for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
