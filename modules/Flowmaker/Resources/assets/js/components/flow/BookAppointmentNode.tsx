import React, { useEffect, useState } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { CalendarCheck, Trash2 } from 'lucide-react';
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

interface BookAppointmentNodeProps {
  id: string;
  data: any;
}

interface BookingService {
  id: number;
  name: string;
  payment_required?: boolean;
  payment_amount?: number | null;
  payment_currency?: string;
}

interface NodeSettings {
  source_name?: string;
  duration_minutes?: string;
  header?: string;
  body?: string;
  footer?: string;
  buttonText?: string;
  success_message?: string;
}

const defaultSettings: NodeSettings = {
  source_name: '',
  duration_minutes: '',
  header: 'Book appointment',
  body: 'Let us find a time that works for you.',
  footer: '',
  buttonText: 'Choose option',
  success_message: 'Your appointment for {{booking_service}} on {{booking_date}} at {{booking_time}} is confirmed.',
};

const BookAppointmentNode = ({ id, data }: BookAppointmentNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();
  const [services, setServices] = useState<BookingService[]>([]);
  const [settings, setSettings] = useState<NodeSettings>({
    ...defaultSettings,
    ...(data.settings || {}),
  });

  useEffect(() => {
    const loadServices = async () => {
      try {
        const response = await fetch('/api/flowmaker/booking-services', {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'include',
        });

        if (!response.ok) {
          return;
        }

        const result = await response.json();
        if (result.success && Array.isArray(result.services)) {
          setServices(result.services);
        }
      } catch (error) {
        console.error('BookAppointmentNode: failed to load services', error);
      }
    };

    loadServices();
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
        <div className="w-[380px] bg-white rounded-lg shadow-lg border border-violet-200 overflow-hidden">
          <Handle
            type="target"
            position={Position.Left}
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />

          <div className="flex items-center gap-2 px-4 py-2 border-b border-violet-100 bg-violet-50">
            <CalendarCheck className="h-4 w-4 text-violet-700" />
            <div className="font-medium text-sm text-violet-800">Book appointment</div>
          </div>

          <div className="p-4 space-y-3 max-h-[420px] overflow-y-auto">
            <div>
              <Label className="text-xs">Fixed service (optional)</Label>
              <select
                className="w-full mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                value={settings.source_name || ''}
                onChange={e => update({ source_name: e.target.value })}
              >
                <option value="">Let customer choose</option>
                {services.map(service => (
                  <option key={service.id} value={service.name}>
                    {service.name}
                    {service.payment_required ? ` — ${service.payment_currency} ${service.payment_amount}` : ''}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <Label className="text-xs">Fixed duration (minutes, optional)</Label>
              <Input
                type="number"
                min={5}
                placeholder="Use service defaults"
                value={settings.duration_minutes || ''}
                onChange={e => update({ duration_minutes: e.target.value })}
              />
            </div>

            <div>
              <Label className="text-xs">Intro header</Label>
              <Input value={settings.header || ''} onChange={e => update({ header: e.target.value })} />
            </div>

            <div>
              <Label className="text-xs">Intro body</Label>
              <Textarea rows={2} value={settings.body || ''} onChange={e => update({ body: e.target.value })} />
            </div>

            <div>
              <Label className="text-xs">Success message</Label>
              <Textarea
                rows={3}
                value={settings.success_message || ''}
                onChange={e => update({ success_message: e.target.value })}
              />
              <p className="text-xs text-gray-400 mt-1">
                Variables: <code>{'{{booking_service}}'}</code>, <code>{'{{booking_date}}'}</code>, <code>{'{{booking_time}}'}</code>
              </p>
            </div>

            <div className="border-t border-gray-100 pt-3 text-xs text-gray-600 space-y-2">
              <p>Guides the contact through service, date, and time selection in WhatsApp.</p>
              <p>If the service requires payment, M-Pesa STK is sent before the booking is confirmed.</p>
            </div>
          </div>

          <Handle
            type="source"
            position={Position.Right}
            id="success"
            style={{ top: '58%', right: -6 }}
            className="!bg-green-500 !w-3 !h-3 !rounded-full !border-2 !border-white"
          />
          <Handle
            type="source"
            position={Position.Right}
            id="unavailable"
            style={{ top: '72%', right: -6 }}
            className="!bg-amber-400 !w-3 !h-3 !rounded-full !border-2 !border-white"
          />
          <Handle
            type="source"
            position={Position.Right}
            id="error"
            style={{ top: '86%', right: -6 }}
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

export default BookAppointmentNode;
