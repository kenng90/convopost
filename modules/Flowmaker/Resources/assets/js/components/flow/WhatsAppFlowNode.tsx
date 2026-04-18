import React, { useState, useEffect } from 'react';
import { Handle, Position } from '@xyflow/react';
import { Send, Trash2, Plus, X } from 'lucide-react';
import { Label } from "@/components/ui/label";
import { NodeData } from '@/types/flow';
import { 
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger 
} from "@/components/ui/context-menu";
import { useFlowActions } from "@/hooks/useFlowActions";

interface WhatsAppFlowNodeProps {
  data: NodeData;
  id: string;
}

interface Condition {
  id: string;
  fieldName: string;
  operator: string;
  value: string;
}

interface NodeSettings {
  whatsappFlowId?: number;
  header?: string;
  footer?: string;
  conditions?: Condition[];
}

interface WhatsAppFlowOption {
  id: number;
  name: string;
  status: string;
  meta_flow_id?: string;
  screen_count?: number;
}

const WhatsAppFlowNode = ({ data, id }: WhatsAppFlowNodeProps) => {
  const { deleteNode } = useFlowActions();
  
  const [selectedFlowId, setSelectedFlowId] = useState<string>(data.settings?.whatsappFlowId || "");
  const [flows, setFlows] = useState<WhatsAppFlowOption[]>([]);
  const [header, setHeader] = useState<string>(data.settings?.header || 'Complete the form');
  const [footer, setFooter] = useState<string>(data.settings?.footer || 'Your responses help us serve you better');
  const [conditions, setConditions] = useState<Condition[]>(data.settings?.conditions || []);

  // Load flows on mount
  useEffect(() => {
    const loadFlows = async () => {
      try {
        console.log('WhatsAppFlowNode: Fetching flows...');
        const response = await fetch('/api/whatsapp-flows', {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'include',
        });

        console.log('WhatsAppFlowNode: Response status:', response.status);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();
        console.log('WhatsAppFlowNode: Flows data received:', result);

        if (result.success && Array.isArray(result.flows)) {
          console.log('WhatsAppFlowNode: Setting flows:', result.flows);
          setFlows(result.flows);
        } else {
          console.warn('WhatsAppFlowNode: Invalid response format:', result);
        }
      } catch (error) {
        console.error('WhatsAppFlowNode: Error loading flows:', error);
      }
    };

    loadFlows();
  }, []);

  // Persist settings to node data
  useEffect(() => {
    if (data && data.settings) {
      data.settings.whatsappFlowId = selectedFlowId;
      data.settings.header = header;
      data.settings.footer = footer;
      data.settings.conditions = conditions;
    }
  }, [selectedFlowId, header, footer, conditions, data]);

  const handleFlowSelect = (value: string) => {
    setSelectedFlowId(value);
    if (data && data.settings) {
      data.settings.whatsappFlowId = value;
    }
  };

  const handleHeaderChange = (value: string) => {
    setHeader(value);
    if (data && data.settings) {
      data.settings.header = value;
    }
  };

  const handleFooterChange = (value: string) => {
    setFooter(value);
    if (data && data.settings) {
      data.settings.footer = value;
    }
  };

  const addCondition = () => {
    const newCondition: Condition = {
      id: Math.random().toString(36).substr(2, 9),
      fieldName: '',
      operator: '==',
      value: '',
    };
    setConditions([...conditions, newCondition]);
  };

  const removeCondition = (conditionId: string) => {
    setConditions(conditions.filter(c => c.id !== conditionId));
  };

  const updateCondition = (conditionId: string, key: keyof Condition, value: string) => {
    setConditions(conditions.map(c =>
      c.id === conditionId ? { ...c, [key]: value } : c
    ));
  };

  const selectedFlow = flows.find(f => f.id.toString() === selectedFlowId);

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="bg-white rounded-lg shadow-lg w-[350px]">
          <Handle
            type="target"
            position={Position.Left}
            style={{ left: '-4px', background: '#555', zIndex: 50 }}
          />
          
          <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
            <Send className="h-4 w-4 text-blue-600" />
            <div className="font-medium">Send WhatsApp Flow</div>
          </div>

          <div className="p-4">
            <div className="space-y-4">
              {/* Header Text */}
              <div className="space-y-2">
                <Label htmlFor="header">Message Header</Label>
                <textarea
                  id="header"
                  placeholder="Complete the form"
                  value={header}
                  onChange={(e) => handleHeaderChange(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
                <p className="text-xs text-gray-500">Text to show above the flow</p>
              </div>

              {/* Flow Selection */}
              <div className="space-y-2">
                <Label htmlFor="flow-select">Select WhatsApp Flow</Label>
                {flows.length === 0 ? (
                  <div className="text-xs text-gray-500 p-2 bg-gray-50 rounded border border-gray-200">
                    No flows found. Create one in WhatsApp Flows.
                  </div>
                ) : (
                  <select
                    id="flow-select"
                    value={selectedFlowId}
                    onChange={(e) => handleFlowSelect(e.target.value)}
                    className="w-full px-2 py-2 text-xs border rounded bg-white"
                  >
                    <option value="">-- Choose a flow --</option>
                    {flows.map(flow => (
                      <option key={flow.id} value={flow.id}>
                        {flow.name} ({flow.status})
                        {flow.meta_flow_id ? ' ✓' : ''}
                      </option>
                    ))}
                  </select>
                )}
              </div>

              {/* Selected Flow Preview */}
              {selectedFlow && (
                <div className="bg-blue-50 border border-blue-200 p-2 rounded text-xs">
                  <div className="font-medium text-blue-900">{selectedFlow.name}</div>
                  <div className="text-gray-600">Status: {selectedFlow.status}</div>
                  {selectedFlow.meta_flow_id && (
                    <div className="text-green-600">Meta ID: {selectedFlow.meta_flow_id}</div>
                  )}
                </div>
              )}

              {/* Footer Text */}
              <div className="space-y-2">
                <Label htmlFor="footer">Message Footer</Label>
                <textarea
                  id="footer"
                  placeholder="Your responses help us serve you better"
                  value={footer}
                  onChange={(e) => handleFooterChange(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
                <p className="text-xs text-gray-500">Text to show below the flow</p>
              </div>

              {/* Conditional Routing */}
              <div className="border-t pt-3">
                <div className="flex items-center justify-between mb-2">
                  <Label className="font-bold text-xs">Route Based on Responses</Label>
                  <button
                    onClick={addCondition}
                    className="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1"
                  >
                    <Plus className="h-3 w-3" /> Add
                  </button>
                </div>

                {conditions.length === 0 ? (
                  <p className="text-xs text-gray-500">No conditions set. Flow will always go to "Completed"</p>
                ) : (
                  <div className="space-y-2">
                    {conditions.map((condition, idx) => (
                      <div key={condition.id} className="bg-gray-50 p-2 rounded border border-gray-200 text-xs">
                        <div className="flex items-start gap-2">
                          <input
                            type="text"
                            placeholder="Field name (e.g., 'contact_preference')"
                            value={condition.fieldName}
                            onChange={(e) => updateCondition(condition.id, 'fieldName', e.target.value)}
                            className="flex-1 px-1.5 py-1 border rounded text-xs"
                          />
                          <select
                            value={condition.operator}
                            onChange={(e) => updateCondition(condition.id, 'operator', e.target.value)}
                            className="px-1.5 py-1 border rounded text-xs"
                          >
                            <option value="==">=</option>
                            <option value="!=">≠</option>
                            <option value="contains">contains</option>
                            <option value="starts">starts</option>
                          </select>
                          <input
                            type="text"
                            placeholder="Value"
                            value={condition.value}
                            onChange={(e) => updateCondition(condition.id, 'value', e.target.value)}
                            className="flex-1 px-1.5 py-1 border rounded text-xs"
                          />
                          <button
                            onClick={() => removeCondition(condition.id)}
                            className="text-red-500 hover:text-red-700"
                          >
                            <X className="h-3 w-3" />
                          </button>
                        </div>
                        <p className="text-gray-500 mt-1">Routes to "Match {idx + 1}" handle if condition is true</p>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Output Handles */}
          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-gray-500">On completion</span>
            <Handle
              type="source"
              position={Position.Right}
              id="onFlowCompleted"
              className="!bg-green-500 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

          {/* Condition Match Handles */}
          {conditions.map((condition, idx) => (
            <div key={condition.id} className="flex items-center justify-between px-4 py-2 border-t border-gray-100 bg-white text-xs">
              <span className="text-gray-500">Match {idx + 1}</span>
              <Handle
                type="source"
                position={Position.Right}
                id={`condition_${idx}`}
                className="!bg-blue-500 !w-3 !h-3 !border-2 !border-white"
              />
            </div>
          ))}

          {/* Else Handle */}
          <div className="flex items-center justify-center px-4 py-2 border-t border-gray-100 bg-white">
            <span className="text-xs text-gray-500 mr-2">
              {conditions.length > 0 ? 'No match' : 'Timeout/Abandoned'}
            </span>
            <Handle
              type="source"
              position={Position.Bottom}
              id="else"
              className="!bg-gray-400 !w-3 !h-3 !border-2 !border-white"
            />
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

export default WhatsAppFlowNode;
