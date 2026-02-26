import React from 'react';
import BaseNodeLayout from './BaseNodeLayout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { useFlowActions } from '../../hooks/useFlowActions';

interface AssignAgentNodeProps {
  id: string;
  data: any;
  selected: boolean;
}

const AssignAgentNode: React.FC<AssignAgentNodeProps> = ({ id, data, selected }) => {
  // Get agents from window.data
  const agents = (window as any).data?.agents || [];
  const { updateNode } = useFlowActions();

  const agentId = data.settings?.agentId;
  const selectedAgent = agents.find((agent: any) => agent.id.toString() === agentId?.toString());

  const handleAgentChange = (value: string) => {
    // Update the node data with the selected agent
    updateNode(id, {
      settings: {
        ...data.settings,
        agentId: value
      }
    });
  };

  return (
    <BaseNodeLayout
      id={id}
      title="Assign to Agent"
      icon="👤"
      headerBackground="bg-blue-50"
      borderColor="border-blue-200"
    >
      <div className="space-y-3">
        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">Agent</label>
          <Select value={agentId?.toString() || ''} onValueChange={handleAgentChange}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Select agent" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Remove Agent Assignment</SelectItem>
              {agents.map((agent: any) => (
                <SelectItem key={agent.id} value={agent.id.toString()}>
                  <div className="flex flex-col">
                    <span className="font-medium">{agent.name}</span>
                    <span className="text-xs text-gray-500">{agent.email}</span>
                  </div>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {selectedAgent ? (
          <div className="text-sm text-gray-600 mt-2">
            <div className="font-medium text-blue-700">{selectedAgent.name}</div>
            <div className="text-xs text-gray-500">{selectedAgent.email}</div>
          </div>
        ) : agentId === 'none' ? (
          <div className="text-sm text-orange-600 font-medium mt-2">Remove Agent Assignment</div>
        ) : (
          <div className="text-sm text-gray-400 italic mt-2">No agent selected</div>
        )}
      </div>
    </BaseNodeLayout>
  );
};

export default AssignAgentNode; 