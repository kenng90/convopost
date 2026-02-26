# Vector Search Integration for LLM Nodes

This document explains how to use the vector search functionality integrated into your Flowmaker LLM nodes.

## Overview

The vector search integration allows your LLM nodes to automatically search through your vectorized knowledge base and include relevant content in AI responses. This enables context-aware conversations that can reference your uploaded documents, FAQs, and website content.

## Features

- **Automatic Context Retrieval**: Searches your knowledge base for relevant content
- **Configurable Similarity**: Set custom similarity thresholds for document matching
- **Multiple Document Types**: Supports PDFs, text files, FAQs, and website content
- **Performance Optimized**: Uses cosine similarity for fast vector comparisons
- **Debug-Friendly**: Comprehensive logging and metadata tracking

## How It Works

1. **Query Processing**: User message or prompt is converted to an embedding vector
2. **Similarity Search**: Cosine similarity calculated against all document chunks in the flow
3. **Context Selection**: Most relevant chunks above the similarity threshold are selected
4. **Context Injection**: Formatted context is added to the OpenRouter API call
5. **Enhanced Response**: LLM responds with knowledge base awareness

## Configuration

Add these settings to your LLM node configuration:

```json
{
  "llm": {
    "model": "openai/gpt-4o-mini",
    "systemPrompt": "You are a helpful assistant...",
    "prompt": "...",
    "enableVectorSearch": true,
    "vectorSearchLimit": 5,
    "similarityThreshold": 0.3,
    "temperature": 0.7,
    "maxTokens": 1000,
    "variableName": "ai_response"
  }
}
```

### Configuration Options

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enableVectorSearch` | boolean | `true` | Enable/disable vector search |
| `vectorSearchLimit` | integer | `5` | Maximum number of documents to include |
| `similarityThreshold` | float | `0.3` | Minimum similarity score (0.0-1.0) |

## Database Schema

The vector search uses two main tables:

### flowdocuments
- `id`: Primary key
- `flow_id`: Foreign key to flows
- `title`: Document title
- `source_type`: Document type (pdf, txt, faq, website, etc.)
- `source_url`: File path or URL
- `content`: Full document content

### embeddedchunks
- `id`: Primary key
- `document_id`: Foreign key to flowdocuments
- `content`: Text chunk content
- `embedding`: JSON array of embedding vector
- `timestamps`

## Usage Examples

### Basic Usage
The vector search runs automatically when enabled. No additional code required.

### Debugging Vector Search
Use the debug method in your code:

```php
use Modules\Flowmaker\Traits\VectorSearch;

class MyClass {
    use VectorSearch;
    
    public function debugSearch() {
        $results = $this->debugVectorSearch(
            'How do I reset my password?', 
            $flowId, 
            10
        );
        
        return $results;
    }
}
```

### Checking Document Count
```php
use Modules\Flowmaker\Models\EmbeddedChunk;

$chunkCount = EmbeddedChunk::countForFlow($flowId);
echo "Flow has {$chunkCount} embedded chunks";
```

## Monitoring and Analytics

### Logged Information
- Vector search results and performance
- Document retrieval statistics
- Similarity scores and thresholds
- Context length and token usage

### Stored Metadata
Each LLM response includes vector search metadata:
```json
{
  "query": "User question truncated...",
  "results_count": 3,
  "top_similarity": 85.2,
  "documents_used": [
    {
      "title": "User Manual",
      "type": "pdf", 
      "similarity": 85.2
    }
  ]
}
```

Access metadata via: `{{ai_response_vector_search_metadata}}`

## Best Practices

### Similarity Thresholds
- **0.8-1.0**: Very similar content (exact matches)
- **0.6-0.8**: Related content (good context)
- **0.3-0.6**: Loosely related (broader context)
- **0.0-0.3**: Likely not relevant

### Optimization Tips
1. **Chunk Size**: Keep document chunks between 200-500 words
2. **Document Quality**: Use clear, well-structured content
3. **Limit Results**: Start with 3-5 documents, adjust based on token limits
4. **Monitor Performance**: Check logs for search timing and relevance

### Troubleshooting
1. **No Results**: Check if documents are embedded for the flow
2. **Low Relevance**: Lower similarity threshold or improve document quality
3. **Performance Issues**: Reduce vectorSearchLimit or implement caching
4. **API Errors**: Verify OpenAI API key is configured in `config('wpbox.openai_api_key')`

## API Integration

### Required API Keys
- **OpenRouter API**: For chat completion (any supported model like gpt-4o-mini, claude, etc.)
- **OpenAI API**: For embeddings using text-embedding-3-small model

### Token Considerations
- Knowledge base context consumes input tokens
- Monitor total token usage for cost optimization
- Adjust maxTokens and vectorSearchLimit accordingly

## Support

For issues or questions:
1. Check application logs for vector search entries
2. Use the debug methods to test similarity calculations
3. Verify document embeddings are properly stored
4. Confirm OpenAI API key is properly configured and has sufficient credits 