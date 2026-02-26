<?php

namespace Modules\Flowmaker\Models;

use Illuminate\Database\Eloquent\Model;

class Flowdocument extends Model
{
    protected $table = 'flowdocuments';
    public $guarded = [];

    public function flow()
    {
        return $this->belongsTo(Flow::class);
    }
    
    public function embeddedchunks()
    {
        return $this->hasMany(EmbeddedChunk::class, 'document_id');
    }
    
    /**
     * Get documents by source type for a specific flow
     */
    public static function getBySourceTypeForFlow($flowId, $sourceType)
    {
        return self::where('flow_id', $flowId)
                   ->where('source_type', $sourceType)
                   ->get();
    }
    
    /**
     * Get formatted data for frontend
     */
    public function getFormattedData()
    {
        switch ($this->source_type) {
            case 'faq':
                return [
                    'id' => 'faq-' . $this->id,
                    'question' => $this->title,
                    'answer' => $this->source_url
                ];
                
            case 'website':
                return [
                    'id' => 'web-' . $this->id,
                    'url' => $this->source_url
                ];
                
            default: // files
                return [
                    'id' => 'file-' . $this->id,
                    'name' => $this->title ?: basename($this->source_url),
                    'type' => strtoupper($this->source_type)
                ];
        }
    }
}