import React from 'react';
import { Variable } from 'lucide-react';
import { NodeData } from '@/types/flow';
import BaseNodeLayout from './BaseNodeLayout';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { useFlowActions } from '@/hooks/useFlowActions';

interface DataStoreNodeProps {
  id: string;
  data: NodeData;
  selected: boolean;
}

const DataStoreNode = ({ id, data, selected }: DataStoreNodeProps) => {
  const { updateNode } = useFlowActions();
  const settings = data.settings || {};

  const handleSettingChange = (key: string, value: any) => {
    updateNode(id, {
      settings: {
        ...settings,
        [key]: value,
      },
    });
  };

  return (
    <BaseNodeLayout
      id={id}
      title={data.label}
      icon={<Variable className="h-4 w-4 text-cyan-600" />}
      headerBackground="bg-cyan-50"
      borderColor="border-cyan-200"
    >
      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor={`variableName-${id}`}>Variable Name</Label>
          <Input
            id={`variableName-${id}`}
            value={settings.variableName || ''}
            onChange={e => handleSettingChange('variableName', e.target.value)}
            placeholder="e.g. my_variable"
            className="bg-cyan-50"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor={`variableValue-${id}`}>Variable Value</Label>
          <Input
            id={`variableValue-${id}`}
            value={settings.variableValue || ''}
            onChange={e => handleSettingChange('variableValue', e.target.value)}
            placeholder="Enter value..."
            className="bg-cyan-50"
          />
        </div>
      </div>
    </BaseNodeLayout>
  );
};

export default DataStoreNode;
