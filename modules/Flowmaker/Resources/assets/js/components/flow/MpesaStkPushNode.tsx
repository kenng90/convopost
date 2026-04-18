import React, { useEffect, useState, useCallback } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Trash2, Smartphone } from 'lucide-react';
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { VariableInput } from '@/components/common/VariableInput';
import { useFlowActions } from "@/hooks/useFlowActions";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";

interface MpesaStkPushNodeProps {
  id: string;
  data: any;
}

interface MpesaSettings {
  amount: string;
  accountReference: string;
  transactionDesc: string;
  responseVar: string;
}

const defaultSettings: MpesaSettings = {
  amount: '',
  accountReference: 'Payment',
  transactionDesc: 'Payment',
  responseVar: 'mpesa_result',
};

const MpesaStkPushNode = ({ id, data }: MpesaStkPushNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();

  const [settings, setSettings] = useState<MpesaSettings>({
    ...defaultSettings,
    ...(data.settings?.mpesa || {}),
  });

  // Persist settings changes to the node data
  useEffect(() => {
    setNodes(nodes =>
      nodes.map(node => {
        if (node.id === id) {
          const currentData = (node.data || {}) as Record<string, unknown>;
          const currentSettings = (currentData.settings || {}) as Record<string, unknown>;
          return {
            ...node,
            data: {
              ...currentData,
              settings: {
                ...currentSettings,
                mpesa: settings,
              },
            },
          };
        }
        return node;
      })
    );
  }, [settings, id, setNodes]);

  const update = (updates: Partial<MpesaSettings>) => {
    setSettings(prev => ({ ...prev, ...updates }));
  };

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[360px] bg-white rounded-lg shadow-lg border border-green-200 overflow-hidden">
          <Handle
            type="target"
            position={Position.Left}
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />

          {/* Header */}
          <div className="flex items-center gap-2 px-4 py-2 border-b border-green-100 bg-green-50">
            <Smartphone className="h-4 w-4 text-green-700" />
            <div className="font-medium text-sm text-green-800">MPesa STK Push</div>
          </div>

          {/* Body */}
          <div className="p-4 space-y-3">
            <div>
              <Label className="text-xs">Amount (KES)</Label>
              <VariableInput
                placeholder="e.g. 100 or {{amount}}"
                value={settings.amount}
                onChange={val => update({ amount: val })}
              />
            </div>

            <div>
              <Label className="text-xs">Account Reference</Label>
              <VariableInput
                placeholder="e.g. ORDER-{{order_id}}"
                value={settings.accountReference}
                onChange={val => update({ accountReference: val })}
              />
              <p className="text-xs text-gray-400 mt-0.5">Max 12 characters</p>
            </div>

            <div>
              <Label className="text-xs">Transaction Description</Label>
              <VariableInput
                placeholder="e.g. Order payment"
                value={settings.transactionDesc}
                onChange={val => update({ transactionDesc: val })}
              />
              <p className="text-xs text-gray-400 mt-0.5">Max 13 characters</p>
            </div>

            <div>
              <Label className="text-xs">Response Variable</Label>
              <Input
                placeholder="e.g. mpesa_result"
                value={settings.responseVar}
                onChange={e => update({ responseVar: e.target.value })}
              />
              <p className="text-xs text-gray-400 mt-0.5">
                Variables set: <code className="bg-gray-100 px-1 rounded">{`{{${settings.responseVar || 'mpesa_result'}_status}}`}</code>, <code className="bg-gray-100 px-1 rounded">{`{{mpesa_receipt}}`}</code>
              </p>
            </div>

            {/* Output labels */}
            <div className="border-t border-gray-100 pt-3 mt-2">
              <p className="text-xs font-medium text-gray-500 mb-2">Outputs</p>
              <div className="flex flex-col gap-2 text-xs text-gray-600">
                <div className="flex items-center justify-between">
                  <span className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-green-500 inline-block" />
                    Payment Successful
                  </span>
                  <span className="text-gray-400 text-[10px]">mpesa-success</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-red-400 inline-block" />
                    Payment Failed / Cancelled
                  </span>
                  <span className="text-gray-400 text-[10px]">mpesa-failed</span>
                </div>
              </div>
            </div>
          </div>

          {/* Success handle */}
          <Handle
            type="source"
            position={Position.Right}
            id="mpesa-success"
            style={{ top: '65%', right: -6 }}
            className="!bg-green-500 !w-3 !h-3 !rounded-full !border-2 !border-white"
          />

          {/* Failed handle */}
          <Handle
            type="source"
            position={Position.Right}
            id="mpesa-failed"
            style={{ top: '80%', right: -6 }}
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

export default MpesaStkPushNode;
