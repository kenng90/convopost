import React, { useEffect, useState } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { TicketX, Trash2 } from 'lucide-react';
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

interface ManageEventRegistrationNodeProps {
  id: string;
  data: any;
}

interface NodeSettings {
  reference_variable?: string;
  default_action?: 'cancel';
  success_message?: string;
}

const defaultSettings: NodeSettings = {
  reference_variable: 'booking_event_reference',
  default_action: 'cancel',
  success_message: 'Your event registration has been cancelled.',
};

const ManageEventRegistrationNode = ({ id, data }: ManageEventRegistrationNodeProps) => {
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
    { id: 'not_found', label: 'Not found', color: 'bg-gray-400' },
    { id: 'error', label: 'Error', color: 'bg-red-400' },
  ];

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[360px] bg-white rounded-lg shadow-lg border border-rose-200 overflow-hidden">
          <Handle type="target" position={Position.Left} className="!bg-gray-300 !w-3 !h-3 !rounded-full" />

          <div className="flex items-center gap-2 px-4 py-2 border-b border-rose-100 bg-rose-50">
            <TicketX className="h-4 w-4 text-rose-700" />
            <div className="font-medium text-sm text-rose-800">Manage event registration</div>
          </div>

          <div className="p-4 space-y-3">
            <div>
              <Label className="text-xs">Reference variable</Label>
              <Input
                value={settings.reference_variable || 'booking_event_reference'}
                onChange={e => update({ reference_variable: e.target.value })}
                className="font-mono text-sm"
              />
            </div>

            <div>
              <Label className="text-xs">Action</Label>
              <select
                className="w-full mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                value={settings.default_action || 'cancel'}
                onChange={e => update({ default_action: e.target.value as NodeSettings['default_action'] })}
              >
                <option value="cancel">Cancel registration</option>
              </select>
            </div>

            <div>
              <Label className="text-xs">Success message</Label>
              <Textarea
                rows={2}
                value={settings.success_message || ''}
                onChange={e => update({ success_message: e.target.value })}
              />
            </div>
          </div>

          {outputs.map(output => (
            <div key={output.id} className="relative border-t px-4 py-2 flex items-center justify-end">
              <span className="text-xs text-gray-700 mr-2 absolute left-4">{output.label}</span>
              <Handle
                type="source"
                position={Position.Right}
                id={output.id}
                className={`!${output.color} !w-3 !h-3 !border-2 !border-white`}
              />
            </div>
          ))}
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

export default ManageEventRegistrationNode;
