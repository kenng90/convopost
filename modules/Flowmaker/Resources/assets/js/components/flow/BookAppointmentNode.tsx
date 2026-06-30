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
  duration_header?: string;
  duration_body?: string;
  date_header?: string;
  date_body?: string;
  slot_header?: string;
  slot_body?: string;
  success_message?: string;
  allow_payment_retry?: boolean;
  allow_pay_at_venue?: boolean;
  booking_webhook_url?: string;
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
              {services.length === 0 ? (
                <p className="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded p-2 mt-1">
                  No bookable services yet.{' '}
                  <a href={window.data?.bookingSetupUrls?.services || '#'} className="underline" target="_blank" rel="noreferrer">
                    Add services in Bookings
                  </a>
                </p>
              ) : (
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
              )}
              {services.length > 10 && (
                <p className="text-xs text-amber-600 mt-1">More than 10 services — customers can page through lists in WhatsApp.</p>
              )}
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
              <Label className="text-xs">Date step header / body</Label>
              <Input className="mb-1" placeholder="Date header" value={settings.date_header || ''} onChange={e => update({ date_header: e.target.value })} />
              <Textarea rows={2} placeholder="Date body" value={settings.date_body || ''} onChange={e => update({ date_body: e.target.value })} />
            </div>

            <div>
              <Label className="text-xs">Time step header / body</Label>
              <Input className="mb-1" placeholder="Time header" value={settings.slot_header || ''} onChange={e => update({ slot_header: e.target.value })} />
              <Textarea rows={2} placeholder="Time body" value={settings.slot_body || ''} onChange={e => update({ slot_body: e.target.value })} />
            </div>

            <div className="space-y-2 border-t border-gray-100 pt-2">
              <label className="flex items-center gap-2 text-xs">
                <input type="checkbox" checked={!!settings.allow_payment_retry} onChange={e => update({ allow_payment_retry: e.target.checked })} />
                Retry M-Pesa payment up to 2 times on failure
              </label>
              <label className="flex items-center gap-2 text-xs">
                <input type="checkbox" checked={!!settings.allow_pay_at_venue} onChange={e => update({ allow_pay_at_venue: e.target.checked })} />
                Allow pay-at-venue when M-Pesa is unavailable
              </label>
            </div>

            <div>
              <Label className="text-xs">Webhook URL (optional)</Label>
              <Input value={settings.booking_webhook_url || ''} onChange={e => update({ booking_webhook_url: e.target.value })} placeholder="https://hooks.example.com/bookings" />
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

          <div className="relative border-t px-4 py-2 flex items-center justify-end">
            <span className="text-xs text-green-700 mr-2 absolute left-4">Confirmed</span>
            <Handle type="source" position={Position.Right} id="success" className="!bg-green-500 !w-3 !h-3 !border-2 !border-white" />
          </div>
          <div className="relative border-t px-4 py-2 flex items-center justify-end">
            <span className="text-xs text-amber-700 mr-2 absolute left-4">Unavailable</span>
            <Handle type="source" position={Position.Right} id="unavailable" className="!bg-amber-400 !w-3 !h-3 !border-2 !border-white" />
          </div>
          <div className="relative border-t px-4 py-2 flex items-center justify-end">
            <span className="text-xs text-red-600 mr-2 absolute left-4">Error</span>
            <Handle type="source" position={Position.Right} id="error" className="!bg-red-400 !w-3 !h-3 !border-2 !border-white" />
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

export default BookAppointmentNode;
