import React from 'react';
import BaseNodeLayout from './BaseNodeLayout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { useFlowActions } from '../../hooks/useFlowActions';

interface AssignGroupNodeProps {
  id: string;
  data: any;
  selected: boolean;
}

const AssignGroupNode: React.FC<AssignGroupNodeProps> = ({ id, data, selected }) => {
  // Get groups from window.data
  const groups = (window as any).data?.groups || [];
  const { updateNode } = useFlowActions();

  const groupId = data.settings?.groupId;
  const action = data.settings?.action || 'add';
  const selectedGroup = groups.find((group: any) => group.id.toString() === groupId?.toString());

  const handleGroupChange = (value: string) => {
    // Update the node data with the selected group
    updateNode(id, {
      settings: {
        ...data.settings,
        groupId: value
      }
    });
  };

  const handleActionChange = (value: string) => {
    // Update the node data with the selected action
    updateNode(id, {
      settings: {
        ...data.settings,
        action: value
      }
    });
  };

  return (
    <BaseNodeLayout
      id={id}
      title="Assign to Group"
      icon="👥"
      headerBackground="bg-green-50"
      borderColor="border-green-200"
    >
      <div className="space-y-3">
        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">Action</label>
          <Select value={action} onValueChange={handleActionChange}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Select action" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="add">Add to Group</SelectItem>
              <SelectItem value="remove">Remove from Group</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">Group</label>
          <Select value={groupId?.toString() || ''} onValueChange={handleGroupChange}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Select group" />
            </SelectTrigger>
            <SelectContent>
              {groups.map((group: any) => (
                <SelectItem key={group.id} value={group.id.toString()}>
                  {group.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {selectedGroup && (
          <div className="text-sm text-gray-600 mt-2">
            <div className="font-medium text-green-700">
              {action === 'add' ? 'Add to' : 'Remove from'}: {selectedGroup.name}
            </div>
          </div>
        )}
      </div>
    </BaseNodeLayout>
  );
};

export default AssignGroupNode; 