import React, { useEffect, useState } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { CalendarDays, Trash2 } from 'lucide-react';
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

interface BookingEventsListNodeProps {
  id: string;
  data: any;
}

interface OccurrencePreview {
  id: string;
  title: string;
  description: string;
  date_label: string;
  time_label: string;
}

interface NodeSettings {
  header?: string;
  body?: string;
  footer?: string;
  buttonText?: string;
  limit?: string;
}

const defaultSettings: NodeSettings = {
  header: 'Upcoming events',
  body: 'Choose an event session to register.',
  footer: '',
  buttonText: 'View events',
  limit: '10',
};

const BookingEventsListNode = ({ id, data }: BookingEventsListNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();
  const [occurrences, setOccurrences] = useState<OccurrencePreview[]>([]);
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
        console.error('BookingEventsListNode: failed to load events', error);
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

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[380px] bg-white rounded-lg shadow-lg border border-sky-200 overflow-hidden">
          <Handle
            type="target"
            position={Position.Left}
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />

          <div className="flex items-center gap-2 px-4 py-2 border-b border-sky-100 bg-sky-50">
            <CalendarDays className="h-4 w-4 text-sky-700" />
            <div className="font-medium text-sm text-sky-800">List events</div>
          </div>

          <div className="p-4 space-y-3 max-h-[420px] overflow-y-auto">
            <div>
              <Label className="text-xs">Intro header</Label>
              <Input value={settings.header || ''} onChange={e => update({ header: e.target.value })} />
            </div>

            <div>
              <Label className="text-xs">Intro body</Label>
              <Textarea rows={2} value={settings.body || ''} onChange={e => update({ body: e.target.value })} />
            </div>

            <div>
              <Label className="text-xs">Max events shown</Label>
              <Input
                type="number"
                min={1}
                max={10}
                value={settings.limit || '10'}
                onChange={e => update({ limit: e.target.value })}
              />
            </div>

            <div className="border-t border-gray-100 pt-3">
              <p className="text-xs font-medium text-gray-600 mb-2">Upcoming sessions preview</p>
              {occurrences.length === 0 ? (
                <p className="text-xs text-gray-400">No published upcoming events found.</p>
              ) : (
                <ul className="space-y-2">
                  {occurrences.slice(0, 5).map(occurrence => (
                    <li key={occurrence.id} className="text-xs border border-gray-100 rounded-md p-2">
                      <p className="font-medium text-gray-800">{occurrence.title}</p>
                      <p className="text-gray-500">{occurrence.date_label} · {occurrence.time_label}</p>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <p className="text-xs text-gray-500">
              Each list row shows the event name with date, time, seats, and price in the description.
            </p>
          </div>

          <div className="border-t px-4 py-2 flex justify-end items-center relative">
            <span className="text-xs text-green-700 absolute left-4">Selected</span>
            <Handle type="source" position={Position.Right} id="selected" className="!bg-green-500 !w-3 !h-3 !border-2 !border-white" />
          </div>
          <div className="border-t px-4 py-2 flex justify-end items-center relative">
            <span className="text-xs text-amber-700 absolute left-4">Empty</span>
            <Handle type="source" position={Position.Right} id="empty" className="!bg-amber-400 !w-3 !h-3 !border-2 !border-white" />
          </div>
          <div className="border-t px-4 py-2 flex justify-end items-center relative">
            <span className="text-xs text-gray-500 absolute left-4">Else</span>
            <Handle type="source" position={Position.Right} id="else" className="!bg-gray-400 !w-3 !h-3 !border-2 !border-white" />
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

export default BookingEventsListNode;
