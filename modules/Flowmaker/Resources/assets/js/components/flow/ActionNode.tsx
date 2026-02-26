import { Handle, Position } from '@xyflow/react';
import { Input } from "@/components/ui/input";
import { useState } from 'react';
import { Trash2, Clock, GitFork } from 'lucide-react';
import { useFlowActions } from "@/hooks/useFlowActions";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

interface ActionNodeData {
  label: string;
  type: 'message' | 'wait' | 'branch';
  settings?: {
    message?: string;
    waitTime?: number;
    waitUnit?: 'seconds' | 'minutes' | 'hours' | 'days';
    conditions?: string[];
  };
}

interface ActionNodeProps {
  id: string;
  data: ActionNodeData;
}

const ActionNode = ({ id, data }: ActionNodeProps) => {
  const [isEditing, setIsEditing] = useState(false);
  const [label, setLabel] = useState(data.label);
  const [settings, setSettings] = useState(data.settings || {});
  const { deleteNode } = useFlowActions();

  const getIcon = () => {
    switch (data.type) {
      case 'wait':
        return <Clock className="h-4 w-4 text-orange-600" />;
      case 'branch':
        return <GitFork className="h-4 w-4 text-purple-600" />;
      default:
        return null;
    }
  };

  const handleLabelChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setLabel(e.target.value);
  };

  const handleSettingChange = (key: string, value: any) => {
    setSettings({ ...settings, [key]: value });
  };

  const renderHandles = () => {
    if (data.type === 'wait') {
      return (
        <>
          <Handle 
            type="target" 
            position={Position.Left} 
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />
          <Handle 
            type="source" 
            position={Position.Right}
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />
        </>
      );
    }
    return (
      <>
        <Handle type="target" position={Position.Top} />
        <Handle type="source" position={Position.Bottom} />
      </>
    );
  };

  const renderSettings = () => {
    switch (data.type) {
      case 'message':
        return (
          <div className="pt-2">
            <Input
              type="text"
              placeholder="Type your message..."
              value={settings.message || ''}
              onChange={(e) => handleSettingChange('message', e.target.value)}
              className="mt-1"
            />
          </div>
        );
      case 'wait':
        return (
          <div className="pt-2 space-y-2">
            <div className="flex gap-2">
              <Input
                type="number"
                placeholder="Duration"
                value={settings.waitTime || ''}
                onChange={(e) => handleSettingChange('waitTime', parseInt(e.target.value))}
                className="flex-1"
                min={0}
              />
              <Select
                value={settings.waitUnit || 'seconds'}
                onValueChange={(value) => handleSettingChange('waitUnit', value)}
              >
                <SelectTrigger className="w-[110px]">
                  <SelectValue placeholder="Unit" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="seconds">Seconds</SelectItem>
                  <SelectItem value="minutes">Minutes</SelectItem>
                  <SelectItem value="hours">Hours</SelectItem>
                  <SelectItem value="days">Days</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        );
      case 'branch':
        return (
          <div className="pt-2">
            <Input
              type="text"
              placeholder="Add a condition..."
              onKeyPress={(e) => {
                if (e.key === 'Enter') {
                  const conditions = settings.conditions || [];
                  handleSettingChange('conditions', [...conditions, (e.target as HTMLInputElement).value]);
                  (e.target as HTMLInputElement).value = '';
                }
              }}
              className="mb-2"
            />
            {(settings.conditions || []).map((condition: string, index: number) => (
              <div key={index} className="text-sm py-1 flex items-center justify-between">
                <span>{condition}</span>
                <button
                  onClick={() => {
                    const newConditions = [...(settings.conditions || [])];
                    newConditions.splice(index, 1);
                    handleSettingChange('conditions', newConditions);
                  }}
                  className="text-gray-400 hover:text-gray-600"
                >
                  ×
                </button>
              </div>
            ))}
          </div>
        );
      default:
        return null;
    }
  };

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="bg-white rounded-lg shadow-md p-3 min-w-[200px]">
          {renderHandles()}
          <div className="space-y-2">
            {isEditing ? (
              <Input
                type="text"
                value={label}
                onChange={handleLabelChange}
                onBlur={() => setIsEditing(false)}
                onKeyPress={(e) => {
                  if (e.key === 'Enter') {
                    setIsEditing(false);
                  }
                }}
                autoFocus
              />
            ) : (
              <div className="flex items-center gap-2">
                {getIcon()}
                <div 
                  className="font-medium cursor-pointer"
                  onClick={() => setIsEditing(true)}
                >
                  {label}
                </div>
              </div>
            )}
            {renderSettings()}
          </div>
        </div>
      </ContextMenuTrigger>
      <ContextMenuContent>
        <ContextMenuItem
          className="text-red-600 focus:text-red-600 focus:bg-red-100"
          onClick={() => deleteNode(id)}
        >
          <Trash2 className="mr-2 h-4 w-4" />
          Delete
        </ContextMenuItem>
      </ContextMenuContent>
    </ContextMenu>
  );
};

export default ActionNode;