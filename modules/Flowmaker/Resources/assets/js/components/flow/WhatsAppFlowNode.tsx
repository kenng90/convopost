import React, { useState, useEffect, useMemo } from 'react';
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

interface FieldMapping {
  id: string;
  formFieldKey: string;
  contactFieldId: string;
}

interface OnCompleteSettings {
  groupId: string;
  journeyId: string;
  stageId: string;
}

interface FormFieldOption {
  key: string;
  label: string;
  type: string;
  screen_title?: string;
}

interface WhatsAppFlowOption {
  id: number;
  name: string;
  status: string;
  meta_flow_id?: string;
  screen_count?: number;
  fields?: FormFieldOption[];
}

interface CustomFieldOption {
  id: number;
  name: string;
}

declare global {
  interface Window {
    data?: {
      groups?: Array<{ id: number; name: string }>;
      journeys?: Array<{ id: number; name: string; stages?: Array<{ id: number; name: string }> }>;
      customFields?: CustomFieldOption[];
    };
  }
}

const WhatsAppFlowNode = ({ data, id }: WhatsAppFlowNodeProps) => {
  const { deleteNode } = useFlowActions();

  const [selectedFlowId, setSelectedFlowId] = useState<string>(String(data.settings?.whatsappFlowId || ""));
  const [flows, setFlows] = useState<WhatsAppFlowOption[]>([]);
  const [header, setHeader] = useState<string>(data.settings?.header || 'Complete the form');
  const [footer, setFooter] = useState<string>(data.settings?.footer || 'Your responses help us serve you better');
  const [conditions, setConditions] = useState<Condition[]>(data.settings?.conditions || []);
  const [fieldMappings, setFieldMappings] = useState<FieldMapping[]>(
    (data.settings?.fieldMappings || []).map((m: any) => ({
      id: m.id || Math.random().toString(36).slice(2, 9),
      formFieldKey: String(m.formFieldKey || ''),
      contactFieldId: String(m.contactFieldId || ''),
    }))
  );
  const [onComplete, setOnComplete] = useState<OnCompleteSettings>({
    groupId: String(data.settings?.onComplete?.groupId || 'none'),
    journeyId: String(data.settings?.onComplete?.journeyId || 'none'),
    stageId: String(data.settings?.onComplete?.stageId || 'none'),
  });

  useEffect(() => {
    const loadFlows = async () => {
      try {
        const response = await fetch('/api/whatsapp-flows', {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'include',
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        if (result.success && Array.isArray(result.flows)) {
          setFlows(result.flows);
        }
      } catch (error) {
        console.error('WhatsAppFormNode: Error loading forms:', error);
      }
    };

    loadFlows();
  }, []);

  useEffect(() => {
    if (data && data.settings) {
      data.settings.whatsappFlowId = selectedFlowId;
      data.settings.header = header;
      data.settings.footer = footer;
      data.settings.conditions = conditions;
      data.settings.fieldMappings = fieldMappings.map(({ formFieldKey, contactFieldId }) => ({
        formFieldKey,
        contactFieldId: contactFieldId === '' ? 'none' : contactFieldId,
      }));
      data.settings.onComplete = onComplete;
    }
  }, [selectedFlowId, header, footer, conditions, fieldMappings, onComplete, data]);

  const handleFlowSelect = (value: string) => {
    setSelectedFlowId(value);
  };

  const addCondition = () => {
    setConditions([
      ...conditions,
      {
        id: Math.random().toString(36).slice(2, 9),
        fieldName: '',
        operator: '==',
        value: '',
      },
    ]);
  };

  const removeCondition = (conditionId: string) => {
    setConditions(conditions.filter((c) => c.id !== conditionId));
  };

  const updateCondition = (conditionId: string, key: keyof Condition, value: string) => {
    setConditions(conditions.map((c) => (c.id === conditionId ? { ...c, [key]: value } : c)));
  };

  const addMapping = () => {
    setFieldMappings([
      ...fieldMappings,
      {
        id: Math.random().toString(36).slice(2, 9),
        formFieldKey: '',
        contactFieldId: '',
      },
    ]);
  };

  const selectedFlow = flows.find((f) => f.id.toString() === selectedFlowId);
  const fieldOptions: FormFieldOption[] = useMemo(
    () => selectedFlow?.fields ?? [],
    [selectedFlow]
  );
  const customFields: CustomFieldOption[] = window.data?.customFields || [];
  const groups = window.data?.groups || [];
  const journeys = window.data?.journeys || [];
  const selectedJourney = journeys.find((j) => String(j.id) === onComplete.journeyId);
  const stages = selectedJourney?.stages || [];

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="bg-white rounded-lg shadow-lg w-[360px]">
          <Handle
            type="target"
            position={Position.Left}
            style={{ left: '-4px', background: '#555', zIndex: 50 }}
          />

          <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
            <Send className="h-4 w-4 text-sky-700" />
            <div className="font-medium">Collect with WhatsApp Form</div>
          </div>

          <div className="p-4">
            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="header">Message Header</Label>
                <textarea
                  id="header"
                  placeholder="Complete the form"
                  value={header}
                  onChange={(e) => setHeader(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="flow-select">Select Live form</Label>
                {flows.length === 0 ? (
                  <div className="text-xs text-gray-500 p-2 bg-gray-50 rounded border border-gray-200">
                    No Live forms found. Publish a form to WhatsApp first.
                  </div>
                ) : (
                  <select
                    id="flow-select"
                    value={selectedFlowId}
                    onChange={(e) => handleFlowSelect(e.target.value)}
                    className="w-full px-2 py-2 text-xs border rounded bg-white"
                  >
                    <option value="">-- Choose a form --</option>
                    {flows.map((flow) => (
                      <option key={flow.id} value={flow.id}>
                        {flow.name}
                      </option>
                    ))}
                  </select>
                )}
              </div>

              {selectedFlow && (
                <div className="bg-sky-50 border border-sky-200 p-2 rounded text-xs">
                  <div className="font-medium text-sky-900">{selectedFlow.name}</div>
                  <div className="text-green-700">Live on WhatsApp</div>
                </div>
              )}

              <div className="space-y-2">
                <Label htmlFor="footer">Message Footer</Label>
                <textarea
                  id="footer"
                  value={footer}
                  onChange={(e) => setFooter(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
              </div>

              <div className="border-t pt-3 space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="font-bold text-xs">Save answers to CRM fields</Label>
                  <button onClick={addMapping} className="text-xs text-blue-600 font-medium flex items-center gap-1">
                    <Plus className="h-3 w-3" /> Map
                  </button>
                </div>
                {fieldMappings.map((mapping) => (
                  <div key={mapping.id} className="flex gap-1 items-center">
                    <select
                      value={mapping.formFieldKey}
                      onChange={(e) =>
                        setFieldMappings(fieldMappings.map((m) =>
                          m.id === mapping.id ? { ...m, formFieldKey: e.target.value } : m
                        ))
                      }
                      className="flex-1 px-1 py-1 border rounded text-xs"
                    >
                      <option value="">Form field</option>
                      {fieldOptions.map((f) => (
                        <option key={f.key} value={f.key}>{f.label}</option>
                      ))}
                    </select>
                    <select
                      value={mapping.contactFieldId}
                      onChange={(e) =>
                        setFieldMappings(fieldMappings.map((m) =>
                          m.id === mapping.id ? { ...m, contactFieldId: e.target.value } : m
                        ))
                      }
                      className="flex-1 px-1 py-1 border rounded text-xs"
                    >
                      <option value="">Contact field</option>
                      {customFields.map((f) => (
                        <option key={f.id} value={f.id}>{f.name}</option>
                      ))}
                    </select>
                    <button
                      onClick={() => setFieldMappings(fieldMappings.filter((m) => m.id !== mapping.id))}
                      className="text-red-500"
                    >
                      <X className="h-3 w-3" />
                    </button>
                  </div>
                ))}
              </div>

              <div className="border-t pt-3 space-y-2">
                <Label className="font-bold text-xs">On complete</Label>
                <select
                  value={onComplete.groupId}
                  onChange={(e) => setOnComplete({ ...onComplete, groupId: e.target.value })}
                  className="w-full px-2 py-1 border rounded text-xs"
                >
                  <option value="none">No group</option>
                  {groups.map((g) => (
                    <option key={g.id} value={g.id}>{g.name}</option>
                  ))}
                </select>
                <select
                  value={onComplete.journeyId}
                  onChange={(e) => setOnComplete({ ...onComplete, journeyId: e.target.value, stageId: 'none' })}
                  className="w-full px-2 py-1 border rounded text-xs"
                >
                  <option value="none">No journey</option>
                  {journeys.map((j) => (
                    <option key={j.id} value={j.id}>{j.name}</option>
                  ))}
                </select>
                <select
                  value={onComplete.stageId}
                  onChange={(e) => setOnComplete({ ...onComplete, stageId: e.target.value })}
                  className="w-full px-2 py-1 border rounded text-xs"
                  disabled={onComplete.journeyId === 'none'}
                >
                  <option value="none">No stage</option>
                  {stages.map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </select>
              </div>

              <div className="border-t pt-3">
                <div className="flex items-center justify-between mb-2">
                  <Label className="font-bold text-xs">Route based on responses</Label>
                  <button onClick={addCondition} className="text-xs text-blue-600 font-medium flex items-center gap-1">
                    <Plus className="h-3 w-3" /> Add
                  </button>
                </div>

                {conditions.length === 0 ? (
                  <p className="text-xs text-gray-500">No conditions. Completions use On completion.</p>
                ) : (
                  <div className="space-y-2">
                    {conditions.map((condition, idx) => (
                      <div key={condition.id} className="bg-gray-50 p-2 rounded border border-gray-200 text-xs">
                        <div className="flex items-start gap-2 flex-wrap">
                          {fieldOptions.length > 0 ? (
                            <select
                              value={condition.fieldName}
                              onChange={(e) => updateCondition(condition.id, 'fieldName', e.target.value)}
                              className="flex-1 min-w-[8rem] px-1.5 py-1 border rounded text-xs"
                            >
                              <option value="">Select field</option>
                              {fieldOptions.map((field) => (
                                <option key={field.key} value={field.key}>
                                  {field.label} ({field.key})
                                </option>
                              ))}
                            </select>
                          ) : (
                            <input
                              type="text"
                              placeholder="Field key"
                              value={condition.fieldName}
                              onChange={(e) => updateCondition(condition.id, 'fieldName', e.target.value)}
                              className="flex-1 px-1.5 py-1 border rounded text-xs"
                            />
                          )}
                          <select
                            value={condition.operator}
                            onChange={(e) => updateCondition(condition.id, 'operator', e.target.value)}
                            className="px-1.5 py-1 border rounded text-xs"
                          >
                            <option value="==">=</option>
                            <option value="!=">≠</option>
                            <option value="contains">contains</option>
                            <option value="starts">starts</option>
                            <option value="gt">&gt;</option>
                            <option value="lt">&lt;</option>
                            <option value="gte">≥</option>
                            <option value="lte">≤</option>
                            <option value="in">in</option>
                          </select>
                          <input
                            type="text"
                            placeholder={condition.operator === 'in' ? 'a,b,c' : 'Value'}
                            value={condition.value}
                            onChange={(e) => updateCondition(condition.id, 'value', e.target.value)}
                            className="flex-1 px-1.5 py-1 border rounded text-xs"
                          />
                          <button onClick={() => removeCondition(condition.id)} className="text-red-500">
                            <X className="h-3 w-3" />
                          </button>
                        </div>
                        <p className="text-gray-500 mt-1">Match {idx + 1}</p>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-gray-500">On completion</span>
            <Handle
              type="source"
              position={Position.Right}
              id="onFlowCompleted"
              className="!bg-green-500 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

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

          <div className="flex items-center justify-between px-4 py-2 border-t border-gray-100 bg-white text-xs">
            <span className="text-gray-500">No match</span>
            <Handle
              type="source"
              position={Position.Right}
              id="else"
              className="!bg-gray-400 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

          <div className="flex items-center justify-center px-4 py-2 border-t border-gray-100 bg-white">
            <span className="text-xs text-gray-500 mr-2">Abandoned / timeout</span>
            <Handle
              type="source"
              position={Position.Bottom}
              id="onAbandoned"
              className="!bg-amber-500 !w-3 !h-3 !border-2 !border-white"
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
