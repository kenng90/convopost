import React from 'react';
import { memo } from 'react';
import { Brain, Plus, Trash2 } from 'lucide-react';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { useFlowActions } from '@/hooks/useFlowActions';
import { NodeData } from '@/types/flow';
import BaseNodeLayout from './BaseNodeLayout';
import { VariableTextArea } from '../common/VariableTextArea';

interface Intention {
  id: string;
  name: string;
  description: string;
}

const LLMNode = memo(({ id, data }: { id: string; data: NodeData }) => {
  const { updateNode } = useFlowActions();
  const settings = data.settings?.llm || {
    model: 'openai/gpt-4o-mini',
    systemPrompt: 'You are a helpful AI assistant. Be concise and professional in your responses.',
    prompt: '',
    temperature: 0.7,
    maxTokens: 1000,
    variableName: '',
    intentions: [] as Intention[]
  };

  // Popular text-only models for easy selection
  const popularModels = [
    { id: 'google/gemini-2.0-flash', name: 'Gemini 2.0 Flash (Google)' },
    { id: 'anthropic/claude-4-sonnet', name: 'Claude Sonnet 4 (Anthropic)' },
    { id: 'google/gemini-2.5-flash-preview-05-20', name: 'Gemini 2.5 Flash Preview 05-20 (Google)' },
    { id: 'deepseek/deepseek-v3-0324-free', name: 'DeepSeek V3 0324 Free (DeepSeek)' },
    { id: 'anthropic/claude-3.7-sonnet', name: 'Claude 3.7 Sonnet (Anthropic)' },
    { id: 'deepseek/deepseek-v3-0324', name: 'DeepSeek V3 0324 (DeepSeek)' },
    { id: 'google/gemini-2.5-pro-preview-06-05', name: 'Gemini 2.5 Pro Preview 06-05 (Google)' },
    { id: 'google/gemini-2.5-pro-preview-05-06', name: 'Gemini 2.5 Pro Preview 05-06 (Google)' },
    { id: 'google/gemini-2.5-flash-preview-04-17', name: 'Gemini 2.5 Flash Preview 04-17 (Google)' },
    { id: 'deepseek/r1-0528-free', name: 'DeepSeek R1 0528 Free (DeepSeek)' },
    { id: 'openai/gpt-4o-mini', name: 'GPT-4o Mini (OpenAI)' },
    { id: 'mistral/mistral-nemo', name: 'Mistral Nemo (Mistral)' },
    { id: 'openai/gpt-4.1', name: 'GPT-4.1 (OpenAI)' },
    { id: 'google/gemini-2.0-flash-lite', name: 'Gemini 2.0 Flash Lite (Google)' },
    { id: 'deepseek/r1-free', name: 'DeepSeek R1 Free (DeepSeek)' },
    { id: 'google/gemini-1.5-flash-8b', name: 'Gemini 1.5 Flash 8B (Google)' },
    { id: 'openai/gpt-4.1-mini', name: 'GPT-4.1 Mini (OpenAI)' },
    { id: 'meta/llama-3.3-70b-instruct', name: 'Llama 3.3 70B Instruct (Meta)' },
    { id: 'google/gemini-1.5-flash', name: 'Gemini 1.5 Flash (Google)' },
    { id: 'xai/grok-3-beta', name: 'Grok 3 Beta (xAI)' }
  ];

  const handleSettingChange = (key: string, value: any) => {
    updateNode(id, {
      settings: {
        ...data.settings,
        llm: {
          ...settings,
          [key]: value
        }
      }
    });
  };

  const addIntention = () => {
    const newIntention: Intention = {
      id: Date.now().toString(),
      name: '',
      description: ''
    };
    const newIntentions = [...(settings.intentions || []), newIntention];
    handleSettingChange('intentions', newIntentions);
  };

  const removeIntention = (intentionId: string) => {
    const newIntentions = (settings.intentions || []).filter((intention: Intention) => intention.id !== intentionId);
    handleSettingChange('intentions', newIntentions);
  };

  const updateIntention = (intentionId: string, field: keyof Intention, value: string) => {
    const newIntentions = (settings.intentions || []).map((intention: Intention) => 
      intention.id === intentionId ? { ...intention, [field]: value } : intention
    );
    handleSettingChange('intentions', newIntentions);
  };

  return (
    <BaseNodeLayout
      id={id}
      title="LLM Text Generation"
      icon={<Brain className="h-4 w-4 text-blue-600" />}
      headerBackground="bg-blue-50"
      borderColor="border-blue-200"
      titleColor="text-blue-700"
    >
      <div className="space-y-4">
        {/* Model Selection */}
        <div className="space-y-2">
          <Label htmlFor="model">Model</Label>
          <select
            id="model"
            value={settings.model}
            onChange={(e) => handleSettingChange('model', e.target.value)}
            className="w-full px-3 py-2 bg-blue-50 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {popularModels.map((model) => (
              <option key={model.id} value={model.id}>
                {model.name}
              </option>
            ))}
          </select>
        </div>

        {/* System Prompt */}
        <div className="space-y-2">
          <Label htmlFor="systemPrompt">System Prompt</Label>
          <VariableTextArea
            value={settings.systemPrompt}
            onChange={(value) => handleSettingChange('systemPrompt', value)}
            placeholder="You are a helpful AI assistant..."
          />
          <p className="text-xs text-gray-500">
            Define the AI's role and behavior. Example: "You are a customer service representative for a tech company. Be friendly and helpful."
          </p>
        </div>

        {/* User Prompt */}
        <div className="space-y-2">
          <Label htmlFor="prompt">User Prompt</Label>
          <VariableTextArea
            value={settings.prompt}
            onChange={(value) => handleSettingChange('prompt', value)}
            placeholder="Enter your prompt here..."
          />
        </div>

        {/* Intentions Section */}
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <Label>Intentions</Label>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={addIntention}
              className="gap-2"
            >
              <Plus className="h-4 w-4" />
              Add Intention
            </Button>
          </div>
          
          {settings.intentions && settings.intentions.length > 0 && (
            <div className="space-y-3">
              {settings.intentions.map((intention: Intention) => (
                <div key={intention.id} className="p-3 border border-gray-200 rounded-md bg-gray-50 space-y-3">
                  <div className="flex items-center justify-between">
                    <Label className="text-sm font-medium">Intention</Label>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => removeIntention(intention.id)}
                      className="text-red-600 hover:text-red-700 hover:bg-red-50"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                  
                  <div className="space-y-2">
                    <Label htmlFor={`intention-name-${intention.id}`} className="text-xs">Intention Name</Label>
                    <Input
                      id={`intention-name-${intention.id}`}
                      value={intention.name}
                      onChange={(e) => updateIntention(intention.id, 'name', e.target.value)}
                      placeholder="e.g., book_appointment, get_info, cancel_order"
                      className="bg-white"
                    />
                  </div>
                  
                  <div className="space-y-2">
                    <Label htmlFor={`intention-desc-${intention.id}`} className="text-xs">Intention Description</Label>
                    <Textarea
                      id={`intention-desc-${intention.id}`}
                      value={intention.description}
                      onChange={(e) => updateIntention(intention.id, 'description', e.target.value)}
                      placeholder="Describe when this intention should be detected..."
                      className="bg-white min-h-[60px]"
                    />
                  </div>
                </div>
              ))}
            </div>
          )}
          
          {(!settings.intentions || settings.intentions.length === 0) && (
            <p className="text-xs text-gray-500">
              Add intentions to help the AI classify user messages and respond appropriately. Each intention will be available as a variable.
            </p>
          )}
        </div>

        {/* Temperature */}
        <div className="space-y-2">
          <Label htmlFor="temperature">Temperature ({settings.temperature})</Label>
          <input
            id="temperature"
            type="range"
            min="0"
            max="2"
            step="0.1"
            value={settings.temperature}
            onChange={(e) => handleSettingChange('temperature', parseFloat(e.target.value))}
            className="w-full"
          />
          <div className="flex justify-between text-xs text-gray-500">
            <span>More focused</span>
            <span>More creative</span>
          </div>
        </div>

        {/* Max Tokens */}
        <div className="space-y-2">
          <Label htmlFor="maxTokens">Max Tokens</Label>
          <Input
            id="maxTokens"
            type="number"
            min="1"
            max="8000"
            value={settings.maxTokens}
            onChange={(e) => handleSettingChange('maxTokens', parseInt(e.target.value))}
            className="bg-blue-50"
          />
        </div>

        {/* Variable Name */}
        <div className="space-y-2">
          <Label htmlFor="variableName">Variable Name</Label>
          <Input
            id="variableName"
            value={settings.variableName || ''}
            onChange={(e) => handleSettingChange('variableName', e.target.value)}
            placeholder="e.g. ai_response"
            className="bg-blue-50"
          />
          <p className="text-xs text-gray-500">
            Store the LLM response in this variable. If intentions are defined, the detected intent will be stored in {settings.variableName || 'variableName'}_intent
          </p>
        </div>
      </div>
    </BaseNodeLayout>
  );
});

LLMNode.displayName = 'LLMNode';

export default LLMNode;
