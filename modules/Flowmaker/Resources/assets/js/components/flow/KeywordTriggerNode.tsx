
import { Key, Plus } from 'lucide-react';
import { Button } from "@/components/ui/button";
import { useState, useEffect } from 'react';
import KeywordInput from './keyword/KeywordInput';
import { useReactFlow } from '@xyflow/react';
import BaseNodeLayout from './BaseNodeLayout';

interface Keyword {
  id: string;
  value: string;
  matchType: 'exact' | 'contains';
}

interface KeywordTriggerNodeProps {
  id: string;
  data: {
    keywords: Keyword[];
  };
}

const KeywordTriggerNode = ({ id, data }: KeywordTriggerNodeProps) => {
  const { setNodes } = useReactFlow();
  const [keywords, setKeywords] = useState<Keyword[]>(data.keywords || []);

  // Update node data whenever keywords change
  useEffect(() => {
    setNodes(nodes => 
      nodes.map(node => {
        if (node.id === id) {
          return {
            ...node,
            data: {
              ...node.data,
              keywords
            }
          };
        }
        return node;
      })
    );
  }, [keywords, id, setNodes]);

  const addKeyword = () => {
    setKeywords([...keywords, { 
      id: Math.random().toString(), 
      value: '', 
      matchType: 'exact' 
    }]);
  };

  const removeKeyword = (keywordId: string) => {
    setKeywords(keywords.filter(k => k.id !== keywordId));
  };

  const updateKeyword = (keywordId: string, field: 'value' | 'matchType', value: string) => {
    setKeywords(keywords.map(k => 
      k.id === keywordId ? { ...k, [field]: value } : k
    ));
  };

  return (
    <BaseNodeLayout
      id={id}
      title="Keyword Trigger"
      icon={<Key className="h-4 w-4 text-yellow-500" />}
      headerBackground="bg-gray-50"
      borderColor="border-gray-100"
      nodeWidth="w-[350px]"
       handles={{ left: true }}
    >
      <div className="text-sm text-gray-700 mb-3">
        <p>This flow will be triggered when specific <strong>keywords</strong> are detected in user messages.</p>
      </div>
      
      <div className="space-y-3">
        {keywords.map((keyword, index) => (
          <KeywordInput
            key={keyword.id}
            {...keyword}
            index={index}
            totalKeywords={keywords.length}
            onUpdate={updateKeyword}
            onRemove={removeKeyword}
          />
        ))}
      </div>

      {keywords.length === 0 && (
        <div className="text-sm text-gray-500 mb-3">
          Add a keyword below to start capturing specific user inputs.
        </div>
      )}

      <Button
        variant="outline"
        size="sm"
        onClick={addKeyword}
        className="w-full flex items-center justify-center mt-3"
      >
        <Plus className="h-4 w-4 mr-2" />
        Add Keyword
      </Button>
    </BaseNodeLayout>
  );
};

export default KeywordTriggerNode;
