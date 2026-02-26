import React from 'react';
import { Handle, Position } from '@xyflow/react';
import { Globe, Trash2 } from 'lucide-react';
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useReactFlow } from '@xyflow/react';
import { NodeData } from '@/types/flow';
import HeadersSection from './http/HeadersSection';
import ParametersSection from './http/ParametersSection';
import { useEffect, useState } from 'react';
import { VariableInput } from '@/components/common/VariableInput';
import { useFlowActions } from "@/hooks/useFlowActions";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";

interface HTTPNodeProps {
  id: string;
  data: NodeData;
}

type HTTPMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

interface HTTPSettings {
  method: HTTPMethod;
  url: string;
  headers: Array<{ id: string; key: string; value: string; }>;
  params: Array<{ id: string; key: string; value: string; }>;
  responseVar: string;
}

const defaultSettings: HTTPSettings = {
  method: 'GET',
  url: '',
  headers: [],
  params: [],
  responseVar: ''
};

const HTTPNode = ({ id, data }: HTTPNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();
  const [httpSettings, setHttpSettings] = useState<HTTPSettings>({
    ...defaultSettings,
    ...(data.settings?.http || {})
  });

  // Update node data whenever settings change
  useEffect(() => {
    setNodes(nodes => 
      nodes.map(node => {
        if (node.id === id) {
          const currentData = (node.data || {}) as Record<string, unknown>;
          const currentSettings = ((currentData.settings || {}) as Record<string, unknown>);
          
          return {
            ...node,
            data: {
              ...currentData,
              settings: {
                ...currentSettings,
                http: httpSettings
              }
            }
          };
        }
        return node;
      })
    );
  }, [httpSettings, id, setNodes]);

  const updateSettings = (updates: Partial<HTTPSettings>) => {
    setHttpSettings(current => ({
      ...current,
      ...updates
    }));
  };

  const addHeader = () => {
    const newHeaders = [...httpSettings.headers, { id: crypto.randomUUID(), key: '', value: '' }];
    updateSettings({ headers: newHeaders });
  };

  const updateHeader = (id: string, key: string, value: string) => {
    const updatedHeaders = httpSettings.headers.map(header =>
      header.id === id ? { ...header, key, value } : header
    );
    updateSettings({ headers: updatedHeaders });
  };

  const removeHeader = (id: string) => {
    const filteredHeaders = httpSettings.headers.filter(header => header.id !== id);
    updateSettings({ headers: filteredHeaders });
  };

  const addParam = () => {
    const newParams = [...httpSettings.params, { id: crypto.randomUUID(), key: '', value: '' }];
    updateSettings({ params: newParams });
  };

  const updateParam = (id: string, key: string, value: string) => {
    const updatedParams = httpSettings.params.map(param =>
      param.id === id ? { ...param, key, value } : param
    );
    updateSettings({ params: updatedParams });
  };

  const removeParam = (id: string) => {
    const filteredParams = httpSettings.params.filter(param => param.id !== id);
    updateSettings({ params: filteredParams });
  };

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[500px] bg-white rounded-lg shadow-lg">
          <Handle 
            type="target" 
            position={Position.Left}
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />
          
          <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
            <Globe className="h-4 w-4 text-blue-600" />
            <div className="font-medium">HTTP Request</div>
          </div>

          <div className="p-4">
            <div className="space-y-4">
              <div className="flex gap-4">
                <div className="w-32">
                  <Label>Methods</Label>
                  <Select
                    value={httpSettings.method}
                    onValueChange={(value: HTTPMethod) => updateSettings({ method: value })}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select method" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="GET">GET</SelectItem>
                      <SelectItem value="POST">POST</SelectItem>
                      <SelectItem value="PUT">PUT</SelectItem>
                      <SelectItem value="DELETE">DELETE</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex-1">
                  <Label>URL</Label>
                  <VariableInput
                    placeholder="Enter URL"
                    value={httpSettings.url}
                    onChange={(value) => updateSettings({ url: value })}
                  />
                </div>
              </div>

              {/* Response Variable Input */}
              <div>
                <Label>Response Variable</Label>
                <Input
                  placeholder="e.g. responseData"
                  value={httpSettings.responseVar}
                  onChange={e => updateSettings({ responseVar: e.target.value })}
                />
              </div>

              <HeadersSection
                headers={httpSettings.headers}
                onAddHeader={addHeader}
                onUpdateHeader={updateHeader}
                onRemoveHeader={removeHeader}
              />

              <ParametersSection
                params={httpSettings.params}
                onAddParam={addParam}
                onUpdateParam={updateParam}
                onRemoveParam={removeParam}
              />
            </div>
          </div>

          <Handle 
            type="source" 
            position={Position.Right}
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />
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

export default HTTPNode;
