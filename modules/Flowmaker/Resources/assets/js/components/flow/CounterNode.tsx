import React, { useState, useCallback } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Calculator, Settings } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { NodeData } from '@/types/flow';

interface CounterNodeProps {
  data: NodeData;
  id: string;
}

interface CounterSettings {
  maxExecutions: number;
  period: 'all_time' | 'last_30_days';
}

const periods = [
  { value: 'all_time', label: 'All Time' },
  { value: 'last_30_days', label: 'Last 30 Days' },
] as const;

const CounterNode = ({ data, id }: CounterNodeProps) => {
  const { setNodes } = useReactFlow();
  const [settings, setSettings] = useState<CounterSettings>(
    data.settings?.counter || { maxExecutions: 1, period: 'all_time' }
  );

  const updateNodeData = useCallback((newSettings: CounterSettings) => {
    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.type === 'counter' && node.id === id) {
          return {
            ...node,
            data: {
              ...node.data,
              settings: {
                ...node.data.settings,
                counter: newSettings,
              },
            },
          };
        }
        return node;
      })
    );
  }, [id, setNodes]);

  const handleMaxExecutionsChange = (value: string) => {
    const maxExecutions = parseInt(value) || 1;
    const newSettings = { ...settings, maxExecutions };
    setSettings(newSettings);
    updateNodeData(newSettings);
  };

  const handlePeriodChange = (value: CounterSettings['period']) => {
    const newSettings = { ...settings, period: value };
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
      
      <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-orange-50">
        <Calculator className="h-4 w-4 text-orange-600" />
        <div className="font-medium">Counter</div>
      </div>

      <div className="p-4">
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-medium">Execution Counter</h3>
            <Settings className="h-4 w-4 text-gray-500" />
          </div>

          <div className="space-y-3">
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">
                Max Executions
              </label>
              <Input
                type="number"
                min="1"
                value={settings.maxExecutions}
                onChange={(e) => handleMaxExecutionsChange(e.target.value)}
                placeholder="Enter max executions"
                className="w-full"
              />
              <p className="text-xs text-gray-500">
                Maximum number of times a contact can execute this flow
              </p>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">
                Time Period
              </label>
              <Select
                value={settings.period}
                onValueChange={handlePeriodChange}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select period" />
                </SelectTrigger>
                <SelectContent>
                  {periods.map((period) => (
                    <SelectItem key={period.value} value={period.value}>
                      {period.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-xs text-gray-500">
                {settings.period === 'all_time' 
                  ? 'Count all executions since the beginning'
                  : 'Count executions in the last 30 days only'
                }
              </p>
            </div>

            <div className="mt-4 p-3 bg-gray-50 rounded-md">
              <p className="text-xs text-gray-600">
                <strong>Logic:</strong> If contact executions {settings.period === 'all_time' ? '(all time)' : '(last 30 days)'} 
                &lt;= {settings.maxExecutions}, go to <span className="text-green-600 font-medium">TRUE</span> path.
                Otherwise, go to <span className="text-red-600 font-medium">FALSE</span> path.
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

export default CounterNode;