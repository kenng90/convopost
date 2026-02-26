
import { Key } from 'lucide-react';
import BaseNodeLayout from './BaseNodeLayout';

interface TriggerNodeProps {
  id: string;
  data: {
    label: string;
    triggerType?: string;
  };
}

const TriggerNode = ({ id, data }: TriggerNodeProps) => {
  return (
    <BaseNodeLayout
      id={id}
      title={data.label}
      icon={<Key className="h-4 w-4 text-yellow-600" />}
      headerBackground="bg-gray-50"
      borderColor="border-gray-100"
      handles={{ right: true }}
    >
      <div className="text-sm text-gray-600">
        <p>This trigger will start your flow when activated.</p>
      </div>
    </BaseNodeLayout>
  );
};

export default TriggerNode;
