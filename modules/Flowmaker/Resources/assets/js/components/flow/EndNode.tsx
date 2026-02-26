
import { Square } from 'lucide-react';
import BaseNodeLayout from './BaseNodeLayout';

interface EndNodeProps {
  id: string;
  data: {
    label: string;
  };
}

const EndNode = ({ id, data }: EndNodeProps) => {
  return (
    <BaseNodeLayout
      id={id}
      title={data.label}
      icon={<Square className="h-4 w-4 text-red-600" />}
      headerBackground="bg-gray-50"
      borderColor="border-gray-100"
      handles={{ left: true }}
    >
      <div className="text-sm text-gray-600">
        <p>This marks the end of your flow.</p>
      </div>
    </BaseNodeLayout>
  );
};

export default EndNode;
