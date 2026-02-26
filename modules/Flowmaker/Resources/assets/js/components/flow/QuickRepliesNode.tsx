import { Handle, Position } from '@xyflow/react';
import { MessageSquare, Plus, X, Trash2, AlertCircle } from 'lucide-react';
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { NodeData } from '@/types/flow';
import { VariableTextArea } from '../common/VariableTextArea';
import { VariableInput } from '../common/VariableInput';
import { useCallback, useState, useEffect } from 'react';
import { useFlowActions } from "@/hooks/useFlowActions";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";

interface QuickRepliesNodeProps {
  data: NodeData;
  id: string;
  selected?: boolean;
  isConnectable?: boolean;
}

interface NodeSettings {
  header: string;
  body: string;
  footer: string;
  activeButtons: number;
  listButtonName: string;
  [key: string]: string | number; // Allow dynamic button keys
}

const QuickRepliesNode = ({ data, id, selected, isConnectable }: QuickRepliesNodeProps) => {
  const { deleteNode } = useFlowActions();
  const [nodeSettings, setNodeSettings] = useState<NodeSettings>(() => {
    const initialSettings: NodeSettings = {
      header: data.settings?.header || '',
      body: data.settings?.body || '',
      footer: data.settings?.footer || '',
      activeButtons: data.settings?.activeButtons || 1,
      listButtonName: data.settings?.listButtonName || '',
    };

    // Add button fields
    for (let i = 1; i <= 10; i++) {
      initialSettings[`button${i}`] = data.settings?.[`button${i}`] || '';
    }

    return initialSettings;
  });

  useEffect(() => {
    if (data.settings) {
      Object.keys(nodeSettings).forEach(key => {
        data.settings[key] = nodeSettings[key];
      });
    }
  }, [nodeSettings, data]);

  const updateNodeData = useCallback((key: string, value: any) => {
    setNodeSettings(prev => ({
      ...prev,
      [key]: value
    }));
  }, []);

  const addButton = useCallback(() => {
    if (nodeSettings.activeButtons < 10) {
      updateNodeData('activeButtons', nodeSettings.activeButtons + 1);
    }
  }, [nodeSettings.activeButtons, updateNodeData]);

  const removeButton = useCallback((buttonIndex: number) => {
    if (nodeSettings.activeButtons > 1) {
      const newSettings = { ...nodeSettings };
      for (let i = buttonIndex; i < 10; i++) {
        newSettings[`button${i}`] = nodeSettings[`button${i + 1}`] || '';
      }
      newSettings[`button${10}`] = '';
      newSettings.activeButtons = nodeSettings.activeButtons - 1;
      setNodeSettings(newSettings);
    }
  }, [nodeSettings]);

  const isListMode = nodeSettings.activeButtons > 3;

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="bg-white rounded-lg shadow-lg w-[300px]">
          <Handle
            type="target"
            position={Position.Left}
            style={{ left: '-4px', background: '#555', zIndex: 50 }}
            isConnectable={isConnectable}
          />

          <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
            <MessageSquare className="h-4 w-4 text-indigo-600" />
            <div className="font-medium">Quick Replies Message</div>
          </div>

          <div className="p-4 space-y-4">
            {isListMode && (
              <div className="bg-yellow-50 border border-yellow-200 rounded-md p-3 flex items-start gap-2">
                <AlertCircle className="h-4 w-4 text-yellow-600 mt-0.5" />
                <div className="text-sm text-yellow-700">
                  With more than 3 buttons, this will be sent as a List Message
                </div>
              </div>
            )}

            <div className="space-y-2">
              <Label htmlFor={`${id}-header`}>Header</Label>
              <VariableInput
                id={`${id}-header`}
                placeholder="Enter header text"
                value={nodeSettings.header}
                onChange={(value) => updateNodeData('header', value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor={`${id}-body`}>Body</Label>
              <VariableTextArea
                value={nodeSettings.body}
                onChange={(value) => updateNodeData('body', value)}
                placeholder="Enter message body"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor={`${id}-footer`}>Footer</Label>
              <VariableInput
                id={`${id}-footer`}
                placeholder="Enter footer text"
                value={nodeSettings.footer}
                onChange={(value) => updateNodeData('footer', value)}
              />
            </div>

            {isListMode && (
              <div className="space-y-2">
                <Label htmlFor={`${id}-list-button`}>List Button Name</Label>
                <VariableInput
                  id={`${id}-list-button`}
                  placeholder="Enter list button name"
                  value={nodeSettings.listButtonName}
                  onChange={(value) => updateNodeData('listButtonName', value)}
                />
              </div>
            )}

            <div className="space-y-3">
              {Array.from({ length: nodeSettings.activeButtons }).map((_, index) => {
                const buttonNum = index + 1;
                return (
                  <div key={buttonNum} className="relative">
                    <div className="flex items-start gap-2">
                      <div className="flex-1">
                        <Label htmlFor={`${id}-button-${buttonNum}`}>Button {buttonNum}</Label>
                        <VariableInput
                          id={`${id}-button-${buttonNum}`}
                          placeholder={`Enter button ${buttonNum} text`}
                          value={nodeSettings[`button${buttonNum}`]}
                          onChange={(value) => updateNodeData(`button${buttonNum}`, value)}
                        />
                      </div>
                      {nodeSettings.activeButtons > 1 && (
                        <Button
                          variant="ghost"
                          size="icon"
                          className="mt-6"
                          onClick={() => removeButton(buttonNum)}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      )}
                    </div>
                    <Handle
                      type="source"
                      position={Position.Right}
                      id={`button-${buttonNum}`}
                      className="!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white"
                      style={{
                        right: '-20px',
                        top: '50%',
                        transform: 'translateY(-50%)',
                        zIndex: 50
                      }}
                      isConnectable={isConnectable}
                    />
                  </div>
                );
              })}
              
              {nodeSettings.activeButtons < 10 && (
                <Button
                  variant="outline"
                  className="w-full"
                  onClick={addButton}
                >
                  <Plus className="h-4 w-4 mr-2" />
                  Add Button
                </Button>
              )}

              <div className="mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2">
                <div className="flex flex-col items-end">
                  <span className="text-xs text-gray-500">Else exit</span>
                  <span className="text-[10px] text-gray-400">Triggered when user reply is not known option</span>
                </div>
                <Handle
                  type="source"
                  position={Position.Right}
                  id="else"
                  className="!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white"
                  style={{ 
                    position: 'relative',
                    right: '-8px',
                    transform: 'translateY(0)',
                    display: 'inline-block'
                  }}
                  isConnectable={isConnectable}
                />
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

export default QuickRepliesNode;
