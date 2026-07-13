import React, { useEffect, useState } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Trash2, PackageCheck } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { VariableTextArea } from '@/components/common/VariableTextArea';
import { useFlowActions } from '@/hooks/useFlowActions';
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from '@/components/ui/context-menu';

interface OrderStatusNodeProps {
  id: string;
  data: any;
}

const STATUSES = ['confirmed', 'preparing', 'shipped', 'delivered', 'cancelled'];

const OrderStatusNode = ({ id, data }: OrderStatusNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();
  const journeys = (window as any).data?.journeys || [];

  const [status, setStatus] = useState(data.settings?.status || 'confirmed');
  const [message, setMessage] = useState(
    data.settings?.message || 'Your order status is now: {{order_status}}. Reference: {{order_reference}}'
  );
  const [journeyId, setJourneyId] = useState(data.settings?.journeyId || 'none');
  const [stageId, setStageId] = useState(data.settings?.stageId || 'none');

  useEffect(() => {
    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.id !== id) return node;
        const currentData = (node.data || {}) as Record<string, unknown>;
        const currentSettings = (currentData.settings || {}) as Record<string, unknown>;
        return {
          ...node,
          data: {
            ...currentData,
            settings: {
              ...currentSettings,
              status,
              message,
              journeyId,
              stageId,
            },
          },
        };
      })
    );
  }, [status, message, journeyId, stageId, id, setNodes]);

  const selectedJourney = journeys.find((j: any) => String(j.id) === String(journeyId));
  const stages = selectedJourney?.stages || [];

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[360px] bg-white rounded-lg shadow-lg border border-amber-200 overflow-hidden">
          <Handle type="target" position={Position.Left} className="!bg-gray-300 !w-3 !h-3 !rounded-full" />
          <div className="bg-amber-50 px-4 py-2 border-b border-amber-100 flex items-center gap-2">
            <PackageCheck className="h-4 w-4 text-amber-700" />
            <span className="text-sm font-semibold text-amber-900">Update order status</span>
          </div>
          <div className="p-4 space-y-3">
            <div>
              <Label className="text-xs">Status</Label>
              <select
                className="w-full border rounded-md h-9 px-2 text-sm"
                value={status}
                onChange={(e) => setStatus(e.target.value)}
              >
                {STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label className="text-xs">Customer message</Label>
              <VariableTextArea value={message} onChange={setMessage} />
            </div>
            <div>
              <Label className="text-xs">Optional journey</Label>
              <select
                className="w-full border rounded-md h-9 px-2 text-sm"
                value={journeyId}
                onChange={(e) => {
                  setJourneyId(e.target.value);
                  setStageId('none');
                }}
              >
                <option value="none">None</option>
                {journeys.map((j: any) => (
                  <option key={j.id} value={j.id}>
                    {j.name}
                  </option>
                ))}
              </select>
            </div>
            {journeyId !== 'none' && (
              <div>
                <Label className="text-xs">Stage</Label>
                <select
                  className="w-full border rounded-md h-9 px-2 text-sm"
                  value={stageId}
                  onChange={(e) => setStageId(e.target.value)}
                >
                  <option value="none">Select stage</option>
                  {stages.map((s: any) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </select>
              </div>
            )}
          </div>
          <Handle type="source" position={Position.Right} className="!bg-amber-400 !w-3 !h-3 !rounded-full" />
        </div>
      </ContextMenuTrigger>
      <ContextMenuContent>
        <ContextMenuItem onClick={() => deleteNode(id)} className="text-red-600">
          <Trash2 className="h-4 w-4 mr-2" /> Delete
        </ContextMenuItem>
      </ContextMenuContent>
    </ContextMenu>
  );
};

export default OrderStatusNode;
