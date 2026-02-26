<?php

namespace Modules\Flowmaker\Models;

use Illuminate\Database\Eloquent\Model;

class EmbeddedChunk extends Model
{
    protected $table = 'embeddedchunks';
    public $guarded = [];

    protected $casts = [
        'embedding' => 'array'
    ];

    public function document()
    {
        return $this->belongsTo(Flowdocument::class, 'document_id');
    }
    
    /**
     * Get embedded chunks for a specific flow
     */
    public static function getForFlow($flowId)
    {
        return self::whereHas('document', function($query) use ($flowId) {
            $query->where('flow_id', $flowId);
        })->with('document')->get();
    }
    
    /**
     * Count embedded chunks for a specific flow
     */
    public static function countForFlow($flowId)
    {
        return self::whereHas('document', function($query) use ($flowId) {
            $query->where('flow_id', $flowId);
        })->count();
    }
} 