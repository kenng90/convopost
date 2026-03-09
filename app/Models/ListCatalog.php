<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;

class ListCatalog extends Model
{
    use HasFactory;

    protected $table = 'list_catalogs';
    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'columns' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parent()
    {
        return $this->belongsTo(ListCatalog::class, 'parent_id');
    }

    public function versions()
    {
        return $this->hasMany(ListCatalog::class, 'parent_id');
    }

    /**
     * Get the root catalog (version 1)
     */
    public function getRootCatalog()
    {
        return $this->parent_id ? $this->parent()->getRootCatalog() : $this;
    }

    /**
     * Get all versions of this catalog
     */
    public function getAllVersions()
    {
        $root = $this->getRootCatalog();
        return collect([$root])->merge($root->versions)->sortBy('version');
    }

    /**
     * Create a new version from current catalog
     */
    public function createNewVersion(array $newData)
    {
        $root = $this->getRootCatalog();
        $maxVersion = $root->getAllVersions()->max('version');

        return self::create([
            'company_id' => $this->company_id,
            'name' => $this->name,
            'version' => $maxVersion + 1,
            'parent_id' => $root->id,
            'description' => $newData['description'] ?? $this->description,
            'items' => $newData['items'] ?? $this->items,
            'columns' => $newData['columns'] ?? $this->columns,
            'source' => $newData['source'] ?? $this->source,
            'original_file_name' => $newData['original_file_name'] ?? $this->original_file_name,
            'metadata' => $newData['metadata'] ?? $this->metadata,
        ]);
    }

    /**
     * Check if catalog is being used in any flows
     */
    public function isInUse()
    {
        // This would check flow nodes that reference this catalog
        // Implementation depends on how you store catalog reference in flow data
        return false; // Placeholder
    }
}
