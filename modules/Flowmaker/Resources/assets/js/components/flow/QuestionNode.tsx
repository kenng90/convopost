import React from 'react';
import { memo } from 'react';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useFlowActions } from '@/hooks/useFlowActions';
import { HelpCircle } from 'lucide-react';
import BaseNodeLayout from './BaseNodeLayout';
import { VariableTextArea } from "../common/VariableTextArea";

const QuestionNode = memo(({ id, data }: { id: string; data: any }) => {
  const { updateNode } = useFlowActions();

  return (
    <BaseNodeLayout
      id={id}
      title="Ask Question"
      icon={<HelpCircle className="h-4 w-4 text-teal-600" />}
      headerBackground="bg-teal-50"
      borderColor="border-teal-200"
    >
      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="question">Question</Label>
          <VariableTextArea
            value={data.settings?.question || ""}
            onChange={(val) =>
              updateNode(id, {
                settings: {
                  ...data.settings,
                  question: val
                }
              })
            }
            placeholder="Enter your question..."
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="variableName">Variable name</Label>
          <Input
            id="variableName"
            value={data.settings?.variableName || ""}
            onChange={(e) => 
              updateNode(id, {
                settings: {
                  ...data.settings,
                  variableName: e.target.value
                }
              })
            }
            placeholder="e.g. user_response"
            className="bg-gray-50"
          />
        </div>
      </div>
    </BaseNodeLayout>
  );
});

QuestionNode.displayName = 'QuestionNode';

export default QuestionNode;
