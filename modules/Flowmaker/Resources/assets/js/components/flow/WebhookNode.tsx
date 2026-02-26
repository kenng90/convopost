
import { Handle, Position } from '@xyflow/react';
import { WebhookIcon, Copy } from 'lucide-react';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useToast } from "@/components/ui/use-toast";
import { WebhookVariable, NodeData } from '@/types/flow';
import { useReactFlow } from '@xyflow/react';

interface WebhookNodeProps {
  data: NodeData;
}

const WebhookNode = ({ data }: WebhookNodeProps) => {
  const { toast } = useToast();
  const { setNodes } = useReactFlow();
  const variables = data.settings?.webhook?.variables || [];
  const webhookUrl = `${window.location.origin}/api/webhook/${Math.random().toString(36).substring(7)}`;

  const handleCopyUrl = () => {
    navigator.clipboard.writeText(webhookUrl);
    toast({
      title: "URL Copied",
      description: "Webhook URL has been copied to clipboard",
    });
  };

  const addVariable = () => {
    const newVariable: WebhookVariable = {
      id: Math.random().toString(36).substring(7),
      name: "",
      type: "string",
      required: true,
    };
    
    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.type === 'webhook') {
          const nodeData = node.data as NodeData;
          return {
            ...node,
            data: {
              ...nodeData,
              settings: {
                ...nodeData.settings,
                webhook: {
                  ...nodeData.settings?.webhook,
                  variables: [...(nodeData.settings?.webhook?.variables || []), newVariable],
                },
              },
            },
          };
        }
        return node;
      })
    );
  };

  const updateVariable = (variableId: string, updates: Partial<WebhookVariable>) => {
    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.type === 'webhook') {
          const nodeData = node.data as NodeData;
          const updatedVariables = variables.map((v) =>
            v.id === variableId ? { ...v, ...updates } : v
          );
          return {
            ...node,
            data: {
              ...nodeData,
              settings: {
                ...nodeData.settings,
                webhook: {
                  ...nodeData.settings?.webhook,
                  variables: updatedVariables,
                },
              },
            },
          };
        }
        return node;
      })
    );
  };

  return (
    <div className="bg-white rounded-lg shadow-lg">
      <Handle 
        type="source" 
        position={Position.Right}
        className="!bg-gray-300 !w-3 !h-3 !rounded-full"
      />
      
      <div className="flex items-center gap-2 border-b border-gray-100 px-3 py-2 bg-gray-50">
        <WebhookIcon className="h-4 w-4 text-blue-600 flex-shrink-0" />
        <div className="font-medium leading-none">Webhook</div>
      </div>

      <div className="p-3">
        <div>
          <Label className="block">Webhook URL</Label>
          <div className="flex items-center gap-2">
            <Input value={webhookUrl} readOnly className="text-sm" />
            <Button variant="outline" size="icon" onClick={handleCopyUrl}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between">
            <Label>Variables</Label>
            <Button variant="outline" size="sm" onClick={addVariable}>
              Add Variable
            </Button>
          </div>
          
          <div>
            {variables.map((variable) => (
              <div key={variable.id} className="flex items-start gap-2 pt-2">
                <Input
                  placeholder="Variable name"
                  value={variable.name}
                  onChange={(e) =>
                    updateVariable(variable.id, { name: e.target.value })
                  }
                  className="flex-1"
                />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default WebhookNode;
