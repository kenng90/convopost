import React, { useEffect, useState } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Settings2, Trash2 } from 'lucide-react';
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

interface ManageBookingNodeProps {
  id: string;
  data: any;
}

interface NodeSettings {
  header?: string;
  body?: string;
  buttonText?: string;
  reference_variable?: string;
  allow_reschedule?: boolean;
  default_action?: 'menu' | 'cancel' | 'reschedule';
}

const defaultSettings: NodeSettings = {
  header: 'Manage your booking',
  body: 'What would you like to do?',
  buttonText: 'Choose',
  reference_variable: 'booking_reference',
  allow_reschedule: true,
  default_action: 'menu',
};

const ManageBookingNode = ({ id, data }: ManageBookingNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();
  const [settings, setSettings] = useState<NodeSettings>({
    ...defaultSettings,
    ...(data.settings || {}),
  });

  useEffect(() => {
    setNodes(nodes =>
      nodes.map(node =>
        node.id === id ? { ...node, data: { ...(node.data || {}), settings } } : node
      )
    );
  }, [settings, id, setNodes]);

  const update = (updates: Partial<NodeSettings>) => setSettings(prev => ({ ...prev, ...updates }));

  const outputs = [
    { id: 'cancelled', label: 'Cancelled', color: 'bg-amber-400' },
    { id: 'rescheduled', label: 'Rescheduled', color: 'bg-green-500' },
    { id: 'not_found', label: 'Not found', color: 'bg-gray-400' },
    { id: 'error', label: 'Error', color: 'bg-red-400' },
  ];

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[360px] bg-white rounded-lg shadow-lg border border-orange-200 overflow-hidden">
          <Handle type="target" position={Position.Left} className="!bg-gray-300 !w-3 !h-3 !rounded-full" />

          <div className="flex items-center gap-2 px-4 py-2 border-b border-orange-100 bg-orange-50">
            <Settings2 className="h-4 w-4 text-orange-700" />
            <div className="font-medium text-sm text-orange-800">Manage booking</div>
          </div>

          <div className="p-4 space-y-3">
            <div>
              <Label className="text-xs">Reference variable</Label>
              <Input
                value={settings.reference_variable || 'booking_reference'}
                onChange={e => update({ reference_variable: e.target.value })}
                className="font-mono text-sm"
              />
            </div>

            <label className="flex items-center gap-2 text-xs">
              <input
                type="checkbox"
                checked={settings.allow_reschedule !== false}
                onChange={e => update({ allow_reschedule: e.target.checked })}
              />
              Allow reschedule in WhatsApp
            </label>

            <div>
              <Label className="text-xs">Default action</Label>
              <select
                className="w-full mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                value={settings.default_action || 'menu'}
                onChange={e => update({ default_action: e.target.value as NodeSettings['default_action'] })}
              >
                <option value="menu">Show cancel / reschedule menu</option>
                <option value="reschedule">Start reschedule (pick date & time)</option>
                <option value="cancel">Start cancel (ask for confirmation)</option>
              </select>
            </div>

            <div>
              <Label className="text-xs">Menu header</Label>
              <Input value={settings.header || ''} onChange={e => update({ header: e.target.value })} />
            </div>

            <div>
              <Label className="text-xs">Menu body</Label>
              <Textarea rows={2} value={settings.body || ''} onChange={e => update({ body: e.target.value })} />
            </div>

            <p className="text-xs text-gray-500">Looks up the contact&apos;s next upcoming booking.</p>
          </div>

          {outputs.map((output, index) => (
            <div key={output.id} className="relative border-t border-gray-100 px-4 py-2 flex justify-end items-center">
              <span className="text-xs text-gray-600 mr-auto">{output.label}</span>
              <Handle
                type="source"
                position={Position.Right}
                id={output.id}
                style={{ top: '50%', right: -6 }}
                className={`!${output.color} !w-3 !h-3 !border-2 !border-white !relative !transform-none`}
              />
            </div>
          ))}
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

export default ManageBookingNode;
