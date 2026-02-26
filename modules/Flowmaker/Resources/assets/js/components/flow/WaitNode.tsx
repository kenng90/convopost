
import { Handle, Position } from '@xyflow/react';
import { Input } from "@/components/ui/input";
import { useState, useEffect } from 'react';
import { Clock } from 'lucide-react';
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
import { Trash2 } from 'lucide-react';

interface WaitNodeData {
  label: string;
  settings?: {
    waitTime?: number;
    waitUnit?: 'seconds' | 'minutes' | 'hours' | 'days';
  };
}

interface WaitNodeProps {
  id: string;
  data: WaitNodeData;
}

const WaitNode = ({ id, data }: WaitNodeProps) => {
  const [isEditing, setIsEditing] = useState(false);
  const [label, setLabel] = useState(data.label);
  const [settings, setSettings] = useState(data.settings || {
    waitTime: 0,
    waitUnit: 'seconds' as const
  });
  const { deleteNode, updateNode } = useFlowActions();

  const handleLabelChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newLabel = e.target.value;
    setLabel(newLabel);
    updateNode(id, { label: newLabel });
  };

  const handleSettingChange = (key: string, value: any) => {
    const newSettings = { ...settings, [key]: value };
    setSettings(newSettings);
    updateNode(id, { settings: newSettings });
  };

  useEffect(() => {
    // Initialize settings if they don't exist
    if (!data.settings?.waitUnit) {
      handleSettingChange('waitUnit', 'seconds');
    }
    if (data.settings?.waitTime === undefined) {
      handleSettingChange('waitTime', 0);
    }
  }, []);

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="bg-white rounded-lg shadow-md p-3 min-w-[200px]">
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
          
          <div className="space-y-2">
            <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50 -mx-3 -mt-3">
              <Clock className="h-4 w-4 text-orange-600" />
              <div className="font-medium">
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
                  <div
                    className="cursor-pointer"
                    onClick={() => setIsEditing(true)}
                  >
                    {label}
                  </div>
                )}
              </div>
            </div>
            
            <div className="pt-2 space-y-2">
              <div className="flex gap-2">
                <Input
                  type="number"
                  placeholder="Duration"
                  value={settings.waitTime}
                  onChange={(e) => handleSettingChange('waitTime', parseInt(e.target.value) || 0)}
                  className="flex-1"
                  min={0}
                />
                <Select
                  value={settings.waitUnit}
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

export default WaitNode;
