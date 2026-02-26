# LLM Node Setup for Flowmaker

This document explains how to set up and use the LLM (Large Language Model) node in Flowmaker using OpenRouter API.

## Configuration

### 1. OpenRouter API Key Setup

Add your OpenRouter API key to your `.env` file:

```env
OPENROUTER_API_KEY=your_openrouter_api_key_here
```

You can get an API key from [OpenRouter.ai](https://openrouter.ai/keys).

### 2. Supported Models

The LLM node supports all models available through OpenRouter, including:

- `google/gemini-2.0-flash` - Gemini 2.0 Flash (Google)
- `anthropic/claude-4-sonnet` - Claude Sonnet 4 (Anthropic)
- `openai/gpt-4o-mini` - GPT-4o Mini (OpenAI)
- `deepseek/deepseek-v3-0324-free` - DeepSeek V3 Free
- And many more...

## Usage

### Node Configuration

The LLM node accepts the following settings:

- **Model**: The LLM model to use (e.g., `openai/gpt-4o-mini`)
- **System Prompt**: Defines the AI's role and behavior
- **User Prompt**: The main prompt/question for the AI
- **Temperature**: Controls randomness (0-2, where 0 is more focused, 2 is more creative)
- **Max Tokens**: Maximum number of tokens in the response (1-8000)
- **Variable Name**: Name of the variable to store the AI response

### Variable Replacement

Both System Prompt and User Prompt support variable replacement using the `{{variable_name}}` syntax:

**Contact Variables:**
- `{{contact_name}}` - Contact's name
- `{{contact_phone}}` - Contact's phone number
- `{{contact_email}}` - Contact's email
- `{{contact_last_message}}` - Last message from contact
- `{{contact_country}}` - Contact's country

**Flow Variables:**
- Any variable set by previous nodes (HTTP responses, question answers, etc.)
- Example: `{{api_response}}`, `{{user_answer}}`, `{{previous_llm_result}}`

### Example Configuration

**System Prompt:**
```
You are a helpful customer service assistant for our company. The customer's name is {{contact_name}} and they are from {{contact_country}}. Be polite and professional. Always respond with structured JSON data containing: sentiment, category, and response.
```

**User Prompt:**
```
Customer message: {{contact_last_message}}
Previous context: {{user_answer}}
Please analyze this message and provide appropriate response.
```

### Response Format

The LLM node automatically requests structured JSON responses from the AI. The response is stored in the specified variable and can be accessed in multiple ways:

1. **Full JSON Response**: `{{variable_name}}`
2. **Individual Fields**: `{{variable_name_field_name}}`

For example, if the AI returns:
```json
{
  "sentiment": "positive",
  "category": "support",
  "response": "Thank you for your message!"
}
```

And your variable name is `ai_analysis`, you can access:
- `{{ai_analysis}}` - Full JSON string
- `{{ai_analysis_sentiment}}` - "positive"
- `{{ai_analysis_category}}` - "support"
- `{{ai_analysis_response}}` - "Thank you for your message!"

## Error Handling

If the API call fails or returns an error, the error message will be stored in the specified variable for debugging purposes.

## Flow Integration

The LLM node can be placed anywhere in your flow and will:
1. Process the prompts with variable replacement
2. Call the OpenRouter API
3. Store the response in the specified variable
4. Continue to the next connected node

## AI Conversation Summary Feature

The LLM node automatically tracks conversation history through an AI summary system:

### How It Works
- When an LLM response contains a `message` field, it's automatically added to an `ai_summary` variable
- When the summary exceeds 1000 characters, it's automatically condensed using AI summarization
- The summary preserves key context while keeping memory manageable

### Accessing the Summary
- Use `{{ai_summary}}` in any node to access the current conversation summary
- The summary is automatically maintained across the entire flow

### Helper Methods
- `$contact->getAISummary($flowId)` - Get current summary
- `$contact->addToAISummary($flowId, $message)` - Add to summary

## Best Practices

1. **Use specific system prompts** to guide the AI's behavior
2. **Request structured data** with `message` field for automatic summarization
3. **Set appropriate temperature** based on your use case
4. **Monitor token usage** to control costs
5. **Use meaningful variable names** for easy reference later
6. **Leverage AI summary** for context-aware conversations

## Troubleshooting

- Check that your OpenRouter API key is correctly set in the `.env` file
- Verify the model name is valid and available
- Check the application logs for detailed error messages
- Ensure your prompts don't exceed the model's context limits 