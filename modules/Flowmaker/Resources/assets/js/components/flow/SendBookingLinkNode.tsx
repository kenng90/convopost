import React, { useEffect, useState } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Link2, Trash2 } from 'lucide-react';
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

interface SendBookingLinkNodeProps {
  id: string;
  data: any;
}

interface BookingService {
  id: number;
  name: string;
}

interface NodeSettings {
  link_type?: 'appointments' | 'events' | 'service' | 'manage' | 'manage_events';
  source_id?: string;
  source_name?: string;
  header?: string;
  message?: string;
  footer?: string;
  booking_webhook_url?: string;
}

const defaultSettings: NodeSettings = {
  link_type: 'appointments',
  source_id: '',
  source_name: '',
  header: '',
  message: 'Book online: {{booking_link}}',
  footer: '',
  booking_webhook_url: '',
};

const SendBookingLinkNode = ({ id, data }: SendBookingLinkNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();
  const [services, setServices] = useState<BookingService[]>([]);
  const [settings, setSettings] = useState<NodeSettings>({
    ...defaultSettings,
    ...(data.settings || {}),
  });

  useEffect(() => {
    fetch('/api/flowmaker/booking-services', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'include',
    })
      .then(r => r.json())
      .then(result => {
        if (result.success && Array.isArray(result.services)) {
          setServices(result.services);
        }
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    setNodes(nodes =>
      nodes.map(node =>
        node.id === id ? { ...node, data: { ...(node.data || {}), settings } } : node
      )
    );
  }, [settings, id, setNodes]);

  const update = (updates: Partial<NodeSettings>) => setSettings(prev => ({ ...prev, ...updates }));

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[360px] bg-white rounded-lg shadow-lg border border-indigo-200 overflow-hidden">
          <Handle type="target" position={Position.Left} className="!bg-gray-300 !w-3 !h-3 !rounded-full" />

          <div className="flex items-center gap-2 px-4 py-2 border-b border-indigo-100 bg-indigo-50">
            <Link2 className="h-4 w-4 text-indigo-700" />
            <div className="font-medium text-sm text-indigo-800">Send booking link</div>
          </div>

          <div className="p-4 space-y-3 max-h-[420px] overflow-y-auto">
            <div>
              <Label className="text-xs">Link type</Label>
              <select
                className="w-full mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                value={settings.link_type || 'appointments'}
                onChange={e => update({ link_type: e.target.value as NodeSettings['link_type'] })}
              >
                <option value="appointments">Appointments catalog</option>
                <option value="events">Events catalog</option>
                <option value="service">Specific service</option>
                <option value="manage">Manage appointment (cancel / reschedule)</option>
                <option value="manage_events">Manage event registration</option>
              </select>
            </div>

            {settings.link_type === 'service' && (
              <div>
                <Label className="text-xs">Service</Label>
                <select
                  className="w-full mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                  value={settings.source_id || ''}
                  onChange={e => {
                    const svc = services.find(s => String(s.id) === e.target.value);
                    update({ source_id: e.target.value, source_name: svc?.name || '' });
                  }}
                >
                  <option value="">Choose service</option>
                  {services.map(s => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </select>
              </div>
            )}

            <div>
              <Label className="text-xs">Message (use {'{{booking_link}}'})</Label>
              <Textarea rows={3} value={settings.message || ''} onChange={e => update({ message: e.target.value })} />
            </div>

            <div>
              <Label className="text-xs">Optional webhook URL override</Label>
              <Input
                placeholder="https://hooks.example.com/bookings"
                value={settings.booking_webhook_url || ''}
                onChange={e => update({ booking_webhook_url: e.target.value })}
              />
            </div>

            <p className="text-xs text-gray-500">
              Sends your public booking page URL. Variable <code>{'{{booking_share_url}}'}</code> is also set on the contact.
            </p>
          </div>

          <div className="relative border-t border-gray-100 px-4 py-3 flex justify-end items-center gap-2">
            <span className="text-xs text-green-700 mr-auto">Sent</span>
            <Handle type="source" position={Position.Right} id="success" className="!bg-green-500 !w-3 !h-3 !border-2 !border-white" />
          </div>
          <div className="relative border-t border-gray-100 px-4 py-2 flex justify-end items-center gap-2">
            <span className="text-xs text-red-600 mr-auto">Error</span>
            <Handle type="source" position={Position.Right} id="error" style={{ top: 'auto', right: -6 }} className="!bg-red-400 !w-3 !h-3 !border-2 !border-white !relative !transform-none" />
          </div>
        </div>
      </ContextMenuTrigger>
      <ContextMenuContent>
        <ContextMenuItem className="text-red-600" onClick={() => deleteNode(id)}>
          <Trash2 className="mr-2 h-4 w-4" /> Delete
        </ContextMenuItem>
      </ContextMenuContent>
    </ContextMenu>
  );
};

export default SendBookingLinkNode;
