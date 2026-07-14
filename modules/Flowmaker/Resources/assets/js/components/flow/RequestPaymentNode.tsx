import React, { useEffect, useState } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Trash2, CreditCard } from 'lucide-react';
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

interface RequestPaymentNodeProps {
  id: string;
  data: any;
}

interface PaymentSettings {
  amount: string;
  accountReference: string;
  description: string;
  provider: 'auto' | 'paystack' | 'mpesa';
  email: string;
  responseVar: string;
}

const defaultSettings: PaymentSettings = {
  amount: '',
  accountReference: 'ORDER',
  description: 'Order payment',
  provider: 'auto',
  email: '',
  responseVar: 'payment_result',
};

const RequestPaymentNode = ({ id, data }: RequestPaymentNodeProps) => {
  const { setNodes } = useReactFlow();
  const { deleteNode } = useFlowActions();

  const [settings, setSettings] = useState<PaymentSettings>({
    ...defaultSettings,
    ...(data.settings?.payment || {}),
  });

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
                payment: settings,
              },
            },
          };
        }
        return node;
      })
    );
  }, [settings, id, setNodes]);

  const update = (updates: Partial<PaymentSettings>) => {
    setSettings(prev => ({ ...prev, ...updates }));
  };

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[360px] bg-white rounded-lg shadow-lg border border-blue-200 overflow-hidden">
          <Handle
            type="target"
            position={Position.Left}
            className="!bg-gray-300 !w-3 !h-3 !rounded-full"
          />

          <div className="flex items-center gap-2 px-4 py-2 border-b border-blue-100 bg-blue-50">
            <CreditCard className="h-4 w-4 text-blue-700" />
            <div className="font-medium text-sm text-blue-800">Request Payment</div>
          </div>

          <div className="p-4 space-y-3">
            <p className="text-xs text-gray-500 leading-relaxed">
              Multi-rail payment for catalog orders. Routes through Paystack or M-Pesa based on provider settings.
            </p>

            <div>
              <Label className="text-xs">Amount</Label>
              <VariableInput
                placeholder="e.g. 100 or {{catalog_order_total_amount}}"
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
              <Label className="text-xs">Description</Label>
              <VariableInput
                placeholder="e.g. Order payment"
                value={settings.description}
                onChange={val => update({ description: val })}
              />
            </div>

            <div>
              <Label className="text-xs">Provider</Label>
              <select
                value={settings.provider}
                onChange={e => update({ provider: e.target.value as PaymentSettings['provider'] })}
                className="w-full px-2 py-2 text-xs border rounded bg-white"
              >
                <option value="auto">Auto</option>
                <option value="paystack">Paystack</option>
                <option value="mpesa">M-Pesa</option>
              </select>
            </div>

            <div>
              <Label className="text-xs">Email (optional)</Label>
              <VariableInput
                placeholder="e.g. {{customer_email}}"
                value={settings.email}
                onChange={val => update({ email: val })}
              />
            </div>

            <div>
              <Label className="text-xs">Response Variable</Label>
              <Input
                placeholder="e.g. payment_result"
                value={settings.responseVar}
                onChange={e => update({ responseVar: e.target.value })}
              />
              <p className="text-xs text-gray-400 mt-0.5">
                Variables set: <code className="bg-gray-100 px-1 rounded">{`{{${settings.responseVar || 'payment_result'}_status}}`}</code>
              </p>
            </div>

            <div className="border-t border-gray-100 pt-3 mt-2">
              <p className="text-xs font-medium text-gray-500 mb-2">Outputs</p>
              <div className="flex flex-col gap-2 text-xs text-gray-600">
                <div className="flex items-center justify-between">
                  <span className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-green-500 inline-block" />
                    Payment Successful
                  </span>
                  <span className="text-gray-400 text-[10px]">success</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-red-400 inline-block" />
                    Payment Failed / Cancelled
                  </span>
                  <span className="text-gray-400 text-[10px]">failed</span>
                </div>
              </div>
            </div>
          </div>

          <Handle
            type="source"
            position={Position.Right}
            id="success"
            style={{ top: '65%', right: -6 }}
            className="!bg-green-500 !w-3 !h-3 !rounded-full !border-2 !border-white"
          />

          <Handle
            type="source"
            position={Position.Right}
            id="failed"
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

export default RequestPaymentNode;
