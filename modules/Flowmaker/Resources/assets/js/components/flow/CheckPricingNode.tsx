import React, { useState, useCallback } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { CreditCard, Settings } from "lucide-react";
import { NodeData } from '@/types/flow';

interface CheckPricingNodeProps {
  data: NodeData;
  id: string;
}

interface PricingSettings {
  freeExecutions: number;
}

const CheckPricingNode = ({ data, id }: CheckPricingNodeProps) => {
  const { setNodes } = useReactFlow();
  const [settings, setSettings] = useState<PricingSettings>(
    data.settings?.pricing || { freeExecutions: 0 }
  );

  const updateNodeData = useCallback((newSettings: PricingSettings) => {
    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.type === 'check_pricing' && node.id === id) {
          return {
            ...node,
            data: {
              ...node.data,
              settings: {
                ...node.data.settings,
                pricing: newSettings,
              },
            },
          };
        }
        return node;
      })
    );
  }, [id, setNodes]);

  const handleFreeExecutionsChange = (value: string) => {
    const freeExecutions = parseInt(value) || 0;
    const newSettings = { ...settings, freeExecutions };
    setSettings(newSettings);
    updateNodeData(newSettings);
  };

  return (
    <div className="bg-white rounded-lg shadow-lg">
      <Handle 
        type="target" 
        position={Position.Left}
        className="!bg-gray-300 !w-3 !h-3 !rounded-full"
      />
      
      <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-green-50">
        <CreditCard className="h-4 w-4 text-green-600" />
        <div className="font-medium">Check User Pricing</div>
      </div>

      <div className="p-4">
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-medium">Credit Check</h3>
            <Settings className="h-4 w-4 text-gray-500" />
          </div>

          <div className="space-y-3">
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">
                Free Executions
              </label>
              <Input
                type="number"
                min="0"
                value={settings.freeExecutions}
                onChange={(e) => handleFreeExecutionsChange(e.target.value)}
                placeholder="Number of free executions"
                className="w-full"
              />
              <p className="text-xs text-gray-500">
                Allow this many executions even with negative credits
              </p>
            </div>

            <div className="mt-4 p-3 bg-gray-50 rounded-md">
              <p className="text-xs text-gray-600 mb-2">
                <strong>Logic:</strong>
              </p>
              <ul className="text-xs text-gray-600 space-y-1">
                <li>• Check if contact credits &gt; -{settings.freeExecutions}</li>
                <li>• If yes: deduct 1 credit and go to <span className="text-green-600 font-medium">TRUE</span> path</li>
                <li>• If no: go to <span className="text-red-600 font-medium">FALSE</span> path</li>
              </ul>
            </div>

            <div className="mt-3 p-2 bg-blue-50 rounded-md">
              <p className="text-xs text-blue-700">
                <strong>Example:</strong> With {settings.freeExecutions} free executions, 
                users can go down to -{settings.freeExecutions} credits before being blocked.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* True/False output handles */}
      <div className="flex justify-between items-center px-4 pb-3">
        <div className="flex flex-col items-center">
          <Handle
            type="source"
            position={Position.Right}
            id="true"
            className="!bg-green-500 !w-3 !h-3 !rounded-full"
            style={{ position: 'relative', right: '-20px', top: '-10px' }}
          />
          <span className="text-xs text-green-600 font-medium mt-1">TRUE</span>
        </div>
        
        <div className="flex flex-col items-center">
          <Handle
            type="source"
            position={Position.Right}
            id="false"
            className="!bg-red-500 !w-3 !h-3 !rounded-full"
            style={{ position: 'relative', right: '-20px', top: '10px' }}
          />
          <span className="text-xs text-red-600 font-medium mt-1">FALSE</span>
        </div>
      </div>
    </div>
  );
};

export default CheckPricingNode;