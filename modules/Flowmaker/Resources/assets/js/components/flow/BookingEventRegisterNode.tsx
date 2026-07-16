import React, { useEffect, useState } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Ticket, Trash2 } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useFlowActions } from '@/hooks/useFlowActions';
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from '@/components/ui/context-menu';

interface BookingEventRegisterNodeProps {
  id: string;
  data: any;
}

interface OccurrenceOption {
  id: string;
  title: string;
  date_label: string;
  time_label: string;
  description?: string;
}

interface NodeSettings {
  occurrence_id?: string;
  party_size?: string;
  intake_mode?: 'lists' | 'form' | 'auto';
  success_message?: string;
  formFieldMap?: {
    occurrenceField?: string;
    partySizeField?: string;
  };
}

const defaultSettings: NodeSettings = {
  occurrence_id: '',
  party_size: '1',
  intake_mode: 'lists',
  success_message: 'You are registered for {{booking_event_title}} on {{booking_event_date}} at {{booking_event_time}}.',
  formFieldMap: {
    occurrenceField: 'occurrence_id',
    partySizeField: 'party_size',
  },
};

const BookingEventRegisterNode = ({ id, data }: BookingEventRegisterNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();
  const [occurrences, setOccurrences] = useState<OccurrenceOption[]>([]);
  const [settings, setSettings] = useState<NodeSettings>({
    ...defaultSettings,
    ...(data.settings || {}),
  });

  useEffect(() => {
    const loadEvents = async () => {
      try {
        const response = await fetch('/api/flowmaker/booking-events', {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'include',
        });

        if (!response.ok) {
          return;
        }

        const result = await response.json();
        if (result.success && Array.isArray(result.occurrences)) {
          setOccurrences(result.occurrences);
        }
      } catch (error) {
        console.error('BookingEventRegisterNode: failed to load events', error);
      }
    };

    loadEvents();
  }, []);

  useEffect(() => {
    setNodes(nodes =>
      nodes.map(node => {
        if (node.id === id) {
          return {
            ...node,
            data: {
              ...(node.data || {}),
              settings,
            },
          };
        }

        return node;
      })
    );
  }, [settings, id, setNodes]);

  const update = (updates: Partial<NodeSettings>) => {
    setSettings(prev => ({ ...prev, ...updates }));
  };

  const selectedOccurrence = occurrences.find(
    occurrence => occurrence.id === (settings.occurrence_id || '')
  );

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[380px] bg-white rounded-lg shadow-lg border border-emerald-200 overflow-hidden">
          <Handle
            type="target"
            position={Position.Left}
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />

          <div className="flex items-center gap-2 px-4 py-2 border-b border-emerald-100 bg-emerald-50">
            <Ticket className="h-4 w-4 text-emerald-700" />
            <div className="font-medium text-sm text-emerald-800">Register for event</div>
          </div>

          <div className="p-4 space-y-3 max-h-[420px] overflow-y-auto">
            <div>
              <Label className="text-xs">Intake mode</Label>
              <select
                className="w-full mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                value={settings.intake_mode || 'lists'}
                onChange={e => update({ intake_mode: e.target.value as NodeSettings['intake_mode'] })}
              >
                <option value="lists">From list / fixed session</option>
                <option value="form">From WhatsApp Form answers</option>
                <option value="auto">Auto — form when answers present</option>
              </select>
            </div>

            {(settings.intake_mode === 'form' || settings.intake_mode === 'auto') && (
              <div className="space-y-2 border border-emerald-100 rounded-md p-2 bg-emerald-50/50">
                <div>
                  <Label className="text-xs">Occurrence field key</Label>
                  <Input
                    className="font-mono text-sm"
                    value={settings.formFieldMap?.occurrenceField || ''}
                    onChange={e => update({
                      formFieldMap: { ...(settings.formFieldMap || {}), occurrenceField: e.target.value },
                    })}
                  />
                </div>
                <div>
                  <Label className="text-xs">Party size field key</Label>
                  <Input
                    className="font-mono text-sm"
                    value={settings.formFieldMap?.partySizeField || ''}
                    onChange={e => update({
                      formFieldMap: { ...(settings.formFieldMap || {}), partySizeField: e.target.value },
                    })}
                  />
                </div>
              </div>
            )}

            <div>
              <Label className="text-xs">Fixed session (optional)</Label>
              <select
                className="w-full mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                value={settings.occurrence_id || ''}
                onChange={e => update({ occurrence_id: e.target.value })}
              >
                <option value="">Use selection from list events node</option>
                {occurrences.map(occurrence => (
                  <option key={occurrence.id} value={occurrence.id}>
                    {occurrence.title} — {occurrence.date_label} {occurrence.time_label}
                  </option>
                ))}
              </select>
              {selectedOccurrence && (
                <p className="text-xs text-gray-500 mt-1">
                  {selectedOccurrence.date_label} · {selectedOccurrence.time_label}
                </p>
              )}
            </div>

            <div>
              <Label className="text-xs">Party size</Label>
              <Input
                type="number"
                min={1}
                value={settings.party_size || '1'}
                onChange={e => update({ party_size: e.target.value })}
              />
            </div>

            <div>
              <Label className="text-xs">Success message</Label>
              <Textarea
                rows={3}
                value={settings.success_message || ''}
                onChange={e => update({ success_message: e.target.value })}
              />
              <p className="text-xs text-gray-400 mt-1">
                Variables: <code>{'{{booking_event_title}}'}</code>, <code>{'{{booking_event_date}}'}</code>, <code>{'{{booking_event_time}}'}</code>
              </p>
            </div>

            <div className="border-t border-gray-100 pt-3 text-xs text-gray-600 space-y-2">
              <p>Registers the contact for the selected event session.</p>
              <p>Wire after a List events node, or pick a fixed session above.</p>
              <p>If payment is required, M-Pesa STK is sent before registration is confirmed.</p>
            </div>
          </div>

          <Handle
            type="source"
            position={Position.Right}
            id="success"
            style={{ top: '62%', right: -6 }}
            className="!bg-green-500 !w-3 !h-3 !rounded-full !border-2 !border-white"
          />
          <Handle
            type="source"
            position={Position.Right}
            id="error"
            style={{ top: '82%', right: -6 }}
            className="!bg-red-400 !w-3 !h-3 !rounded-full !border-2 !border-white"
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

export default BookingEventRegisterNode;
