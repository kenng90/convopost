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
  allOf?: Array<{ fieldName: string; operator: string; value: string }>;
}

interface ScoreRule {
  id: string;
  fieldName: string;
  operator: string;
  value: string;
  points: string;
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
  live?: boolean;
  lifecycle?: string;
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
  const [scoreRules, setScoreRules] = useState<ScoreRule[]>(
    (data.settings?.scoreRules || []).map((r: any) => ({
      id: r.id || Math.random().toString(36).slice(2, 9),
      fieldName: String(r.fieldName || ''),
      operator: String(r.operator || '=='),
      value: String(r.value || ''),
      points: String(r.points ?? '10'),
    }))
  );
  const [scoreThreshold, setScoreThreshold] = useState<string>(
    data.settings?.scoreThreshold != null ? String(data.settings.scoreThreshold) : ''
  );
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
        const response = await fetch('/api/whatsapp-flows?include_drafts=1', {
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
      const flow = flows.find((f) => f.id.toString() === selectedFlowId);
      data.settings.whatsappFlowId = selectedFlowId;
      data.settings.header = header;
      data.settings.footer = footer;
      data.settings.conditions = conditions;
      data.settings.scoreRules = scoreRules.map(({ fieldName, operator, value, points }) => ({
        fieldName,
        operator,
        value,
        points: Number(points) || 0,
      }));
      data.settings.scoreThreshold = scoreThreshold === '' ? null : Number(scoreThreshold);
      data.settings.fieldMappings = fieldMappings.map(({ formFieldKey, contactFieldId }) => ({
        formFieldKey,
        contactFieldId: contactFieldId === '' ? 'none' : contactFieldId,
      }));
      data.settings.onComplete = onComplete;
      data.settings.formFields = flow?.fields || data.settings.formFields || [];
    }
  }, [selectedFlowId, header, footer, conditions, scoreRules, scoreThreshold, fieldMappings, onComplete, data, flows]);

  const handleFlowSelect = (value: string) => {
    setSelectedFlowId(value);
    const flow = flows.find((f) => f.id.toString() === value);
    if (data?.settings) {
      data.settings.formFields = flow?.fields || [];
    }
  };

  const addCondition = () => {
    setConditions([
      ...conditions,
      {
        id: Math.random().toString(36).slice(2, 9),
        fieldName: '',
        operator: '==',
        value: '',
        allOf: [],
      },
    ]);
  };

  const addAndClause = (conditionId: string) => {
    setConditions(conditions.map((c) => {
      if (c.id !== conditionId) return c;
      return {
        ...c,
        allOf: [
          ...(c.allOf || []),
          { fieldName: '', operator: '==', value: '' },
        ],
      };
    }));
  };

  const updateAndClause = (
    conditionId: string,
    clauseIndex: number,
    key: 'fieldName' | 'operator' | 'value',
    value: string
  ) => {
    setConditions(conditions.map((c) => {
      if (c.id !== conditionId) return c;
      const allOf = [...(c.allOf || [])];
      allOf[clauseIndex] = { ...allOf[clauseIndex], [key]: value };
      return { ...c, allOf };
    }));
  };

  const removeAndClause = (conditionId: string, clauseIndex: number) => {
    setConditions(conditions.map((c) => {
      if (c.id !== conditionId) return c;
      return { ...c, allOf: (c.allOf || []).filter((_, i) => i !== clauseIndex) };
    }));
  };

  const addScoreRule = () => {
    setScoreRules([
      ...scoreRules,
      {
        id: Math.random().toString(36).slice(2, 9),
        fieldName: '',
        operator: '==',
        value: '',
        points: '10',
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
                <Label htmlFor="flow-select">Select form</Label>
                {flows.length === 0 ? (
                  <div className="text-xs text-gray-500 p-2 bg-gray-50 rounded border border-gray-200">
                    No forms found. Create a WhatsApp Form first. Draft forms can be wired for simulation; Live is required to send.
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
                        {flow.name}{flow.live || flow.meta_flow_id ? ' (Live)' : ' (Draft — simulate only)'}
                      </option>
                    ))}
                  </select>
                )}
              </div>

              {selectedFlow && (
                <div className={`border p-2 rounded text-xs space-y-2 ${(selectedFlow.live || selectedFlow.meta_flow_id) ? 'bg-sky-50 border-sky-200' : 'bg-amber-50 border-amber-200'}`}>
                  <div className="font-medium text-sky-900">{selectedFlow.name}</div>
                  <div className={(selectedFlow.live || selectedFlow.meta_flow_id) ? 'text-green-700' : 'text-amber-700'}>
                    {selectedFlow.lifecycle || ((selectedFlow.live || selectedFlow.meta_flow_id) ? 'Live on WhatsApp' : 'Draft — publish to send')}
                  </div>
                  <div className="border-t border-sky-200/80 pt-2 space-y-1">
                    <div className="font-medium text-sky-900">Variables after submit</div>
                    <p className="text-gray-600">
                      Use in HTTP, Message, or Payment nodes connected to <strong>On completion</strong>:
                    </p>
                    <code className="block bg-white/80 border border-sky-200 rounded px-2 py-1 text-[11px] text-sky-900">
                      {'{{whatsapp_flow_responses}}'}
                    </code>
                    <p className="text-gray-500">All answers as JSON. Or per field:</p>
                    {fieldOptions.length > 0 ? (
                      <div className="flex flex-wrap gap-1">
                        {fieldOptions.map((f) => (
                          <code key={f.key} className="bg-white/80 border border-sky-100 rounded px-1.5 py-0.5 text-[10px] text-sky-800">
                            {`{{form_${f.key}}}`}
                          </code>
                        ))}
                      </div>
                    ) : (
                      <code className="block bg-white/80 border border-sky-100 rounded px-2 py-1 text-[10px] text-sky-800">
                        {'{{form_<field_key>}}'}
                      </code>
                    )}
                  </div>
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
                      <div key={condition.id} className="bg-gray-50 p-2 rounded border border-gray-200 text-xs space-y-2">
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
                        {(condition.allOf || []).map((clause, clauseIdx) => (
                          <div key={clauseIdx} className="flex items-center gap-1 pl-2 border-l-2 border-sky-300">
                            <span className="text-[10px] text-sky-700 font-medium">AND</span>
                            <select
                              value={clause.fieldName}
                              onChange={(e) => updateAndClause(condition.id, clauseIdx, 'fieldName', e.target.value)}
                              className="flex-1 px-1 py-1 border rounded text-xs"
                            >
                              <option value="">Field</option>
                              {fieldOptions.map((field) => (
                                <option key={field.key} value={field.key}>{field.label}</option>
                              ))}
                            </select>
                            <select
                              value={clause.operator}
                              onChange={(e) => updateAndClause(condition.id, clauseIdx, 'operator', e.target.value)}
                              className="px-1 py-1 border rounded text-xs"
                            >
                              <option value="==">=</option>
                              <option value="!=">≠</option>
                              <option value="contains">contains</option>
                              <option value="gt">&gt;</option>
                              <option value="gte">≥</option>
                              <option value="in">in</option>
                            </select>
                            <input
                              type="text"
                              value={clause.value}
                              onChange={(e) => updateAndClause(condition.id, clauseIdx, 'value', e.target.value)}
                              className="flex-1 px-1 py-1 border rounded text-xs"
                              placeholder="Value"
                            />
                            <button onClick={() => removeAndClause(condition.id, clauseIdx)} className="text-red-500">
                              <X className="h-3 w-3" />
                            </button>
                          </div>
                        ))}
                        <div className="flex items-center justify-between">
                          <p className="text-gray-500">Match {idx + 1}</p>
                          <button onClick={() => addAndClause(condition.id)} className="text-[10px] text-sky-700 font-medium">
                            + AND clause
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div className="border-t pt-3 space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="font-bold text-xs">Lead score (optional)</Label>
                  <button onClick={addScoreRule} className="text-xs text-blue-600 font-medium flex items-center gap-1">
                    <Plus className="h-3 w-3" /> Rule
                  </button>
                </div>
                <input
                  type="number"
                  placeholder="Pass threshold (e.g. 20)"
                  value={scoreThreshold}
                  onChange={(e) => setScoreThreshold(e.target.value)}
                  className="w-full px-2 py-1 border rounded text-xs"
                />
                {scoreRules.map((rule) => (
                  <div key={rule.id} className="flex gap-1 items-center flex-wrap">
                    <select
                      value={rule.fieldName}
                      onChange={(e) =>
                        setScoreRules(scoreRules.map((r) =>
                          r.id === rule.id ? { ...r, fieldName: e.target.value } : r
                        ))
                      }
                      className="flex-1 min-w-[6rem] px-1 py-1 border rounded text-xs"
                    >
                      <option value="">Field</option>
                      {fieldOptions.map((f) => (
                        <option key={f.key} value={f.key}>{f.label}</option>
                      ))}
                    </select>
                    <select
                      value={rule.operator}
                      onChange={(e) =>
                        setScoreRules(scoreRules.map((r) =>
                          r.id === rule.id ? { ...r, operator: e.target.value } : r
                        ))
                      }
                      className="px-1 py-1 border rounded text-xs"
                    >
                      <option value="==">=</option>
                      <option value="gte">≥</option>
                      <option value="gt">&gt;</option>
                      <option value="contains">contains</option>
                      <option value="in">in</option>
                    </select>
                    <input
                      type="text"
                      value={rule.value}
                      onChange={(e) =>
                        setScoreRules(scoreRules.map((r) =>
                          r.id === rule.id ? { ...r, value: e.target.value } : r
                        ))
                      }
                      className="w-16 px-1 py-1 border rounded text-xs"
                      placeholder="Value"
                    />
                    <input
                      type="number"
                      value={rule.points}
                      onChange={(e) =>
                        setScoreRules(scoreRules.map((r) =>
                          r.id === rule.id ? { ...r, points: e.target.value } : r
                        ))
                      }
                      className="w-14 px-1 py-1 border rounded text-xs"
                      placeholder="Pts"
                    />
                    <button
                      onClick={() => setScoreRules(scoreRules.filter((r) => r.id !== rule.id))}
                      className="text-red-500"
                    >
                      <X className="h-3 w-3" />
                    </button>
                  </div>
                ))}
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

          {scoreThreshold !== '' && (
            <>
              <div className="flex items-center justify-between px-4 py-2 border-t border-gray-100 bg-white text-xs">
                <span className="text-emerald-700">Score pass</span>
                <Handle type="source" position={Position.Right} id="score_pass" className="!bg-emerald-500 !w-3 !h-3 !border-2 !border-white" />
              </div>
              <div className="flex items-center justify-between px-4 py-2 border-t border-gray-100 bg-white text-xs">
                <span className="text-amber-700">Score fail</span>
                <Handle type="source" position={Position.Right} id="score_fail" className="!bg-amber-500 !w-3 !h-3 !border-2 !border-white" />
              </div>
            </>
          )}

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
