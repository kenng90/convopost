
import { MessageCircle } from 'lucide-react';
import { Label } from "@/components/ui/label";
import { useCallback } from 'react';
import { useReactFlow } from '@xyflow/react';
import BaseNodeLayout from './BaseNodeLayout';
import { VariableTextArea } from '../common/VariableTextArea';

interface MessageNodeProps {
  id: string;
  data: {
    label: string;
    settings?: {
      message?: string;
    };
  };
}

const MessageNode = ({ id, data }: MessageNodeProps) => {
  const { setNodes } = useReactFlow();

  const handleMessageChange = useCallback((value: string) => {
    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.id === id) {
          const currentData = typeof node.data === "object" && node.data !== null ? node.data : {};
          const currentSettings = typeof currentData.settings === "object" && currentData.settings !== null ? currentData.settings : {};
          
          return {
            ...node,
            data: {
              ...currentData,
              settings: {
                ...currentSettings,
                message: value,
              },
            },
          };
        }
        return node;
      })
    );
  }, [id, setNodes]);

  return (
    <BaseNodeLayout
      id={id}
      title="Send Message"
      icon={<MessageCircle className="h-4 w-4 text-blue-600" />}
      headerBackground="bg-gray-50"
      borderColor="border-gray-100"
    >
      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="message">Message</Label>
          <VariableTextArea
            value={data.settings?.message || ""}
            onChange={handleMessageChange}
            placeholder="Enter your message..."
          />
        </div>
      </div>
    </BaseNodeLayout>
  );
};

export default MessageNode;
