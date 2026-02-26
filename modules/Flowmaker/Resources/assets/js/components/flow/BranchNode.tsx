import React, { useState, useCallback } from 'react';
import { Handle, Position, useReactFlow } from '@xyflow/react';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { GitFork, Plus, X } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { BranchCondition, WebhookVariable, NodeData } from '@/types/flow';
import { useFlowVariables } from '@/hooks/useFlowVariables';

interface BranchNodeProps {
  data: NodeData;
  id: string;
}

interface NodeChangeEvent {
    element: Element;
    parents: Node[];
    selectionChange?: boolean;
    initial?: boolean;
}

const operators = [
  { value: 'equals', label: 'Equals' },
  { value: 'not_equals', label: 'Not Equals' },
  { value: 'greater_than', label: 'Greater Than' },
  { value: 'less_than', label: 'Less Than' },
  { value: 'contains', label: 'Contains' },
  { value: 'not_contains', label: 'Not Contains' },
] as const;

const BranchNode = ({ data, id }: BranchNodeProps) => {
  const { getNodes, setNodes } = useReactFlow();
  const { groupedVariables } = useFlowVariables();
  const [conditions, setConditions] = useState<BranchCondition[]>(
    data.settings?.conditions || []
  );

  //console.log('data for node', data);
  
  const webhookNode = getNodes().find((node) => node.type === 'webhook');
  const webhookVariables = (webhookNode?.data as NodeData)?.settings?.webhook?.variables || [];

  const updateNodeData = useCallback((newConditions: BranchCondition[]) => {
    //console.log('updateNodeData', newConditions);
    //console.log('node id', id);
    
    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.type === 'branch' && node.id === id) {
          console.log('node data', node.data);
          return {
            ...node,
            data: {
              ...node.data,
              settings: {
                ...node.data.settings,
                conditions: newConditions,
              },
            },
          };
        }
        return node;
      })
    );
  }, [id, setNodes]);

  const addCondition = (nodeId: string) => {
    const newCondition: BranchCondition = {
      id: Math.random().toString(36).substring(7),
      nodeId: nodeId,
      variableId: '',
      operator: 'equals',
      value: '',
    };
    const newConditions = [...conditions, newCondition];
    setConditions(newConditions);
    updateNodeData(newConditions);
  };

  const removeCondition = (conditionId: string) => {
    const newConditions = conditions.filter((c) => c.id !== conditionId);
    setConditions(newConditions);
    updateNodeData(newConditions);
  };

  return (
    <div className="bg-white rounded-lg shadow-lg">
      <Handle 
        type="target" 
        position={Position.Left}
        className="!bg-gray-300 !w-3 !h-3 !rounded-full"
      />
      
      <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
        <GitFork className="h-4 w-4 text-purple-600" />
        <div className="font-medium">Branch</div>
      </div>

      <div className="p-4">
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-medium">Branch</h3>
            <Button variant="outline" size="sm" onClick={() => addCondition(id)}>
              <Plus className="h-4 w-4 mr-1" />
              Add Condition
            </Button>
          </div>

          <div className="space-y-3">
            {conditions.map((condition) => (
              <div key={condition.id} className="flex items-center gap-2">
                <Select
                  value={condition.variableId}
                  onValueChange={(value) => {
                    const updatedConditions = conditions.map((c) =>
                      c.id === condition.id ? { ...c, variableId: value } : c
                    );
                    setConditions(updatedConditions);
                    updateNodeData(updatedConditions);
                  }}
                >
                  <SelectTrigger className="w-[180px]">
                    <SelectValue placeholder="Variable" />
                  </SelectTrigger>
                  <SelectContent>
                    {Object.entries(groupedVariables).map(([category, categoryVariables]) => (
                      <div key={category}>
                        <div className="px-2 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                          {category}
                        </div>
                        {categoryVariables.map((variable) => (
                          <SelectItem key={variable.value} value={variable.value}>
                            {variable.label}
                          </SelectItem>
                        ))}
                      </div>
                    ))}
                  </SelectContent>
                </Select>

                <Select
                  value={condition.operator}
                  onValueChange={(value: BranchCondition['operator']) => {
                    const updatedConditions = conditions.map((c) =>
                      c.id === condition.id ? { ...c, operator: value } : c
                    );
                    setConditions(updatedConditions);
                    updateNodeData(updatedConditions);
                  }}
                >
                  <SelectTrigger className="w-[120px]">
                    <SelectValue placeholder="Operator" />
                  </SelectTrigger>
                  <SelectContent>
                    {operators.map((op) => (
                      <SelectItem key={op.value} value={op.value}>
                        {op.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                <Input
                  placeholder="Value"
                  value={condition.value}
                  onChange={(e) => {
                    const updatedConditions = conditions.map((c) =>
                      c.id === condition.id ? { ...c, value: e.target.value } : c
                    );
                    setConditions(updatedConditions);
                    updateNodeData(updatedConditions);
                  }}
                  className="flex-1"
                />

                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => removeCondition(condition.id)}
                >
                  <X className="h-4 w-4" />
                </Button>

                <div className="flex flex-col gap-4 ml-2">
                  <Handle
                    type="source"
                    position={Position.Right}
                    id={`condition-${condition.id}-true`}
                    className="!bg-green-500 !w-3 !h-3 !rounded-full"
                    style={{ top: '25%' }}
                  />
                  <Handle
                    type="source"
                    position={Position.Right}
                    id={`condition-${condition.id}-false`}
                    className="!bg-red-500 !w-3 !h-3 !rounded-full"
                    style={{ top: '75%' }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default BranchNode;