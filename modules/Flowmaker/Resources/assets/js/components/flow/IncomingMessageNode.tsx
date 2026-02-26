
import { MessageSquare } from 'lucide-react';
import BaseNodeLayout from './BaseNodeLayout';

interface IncomingMessageNodeProps {
  id: string;
  data: {
    label: string;
  };
}

const IncomingMessageNode = ({ id, data }: IncomingMessageNodeProps) => {
  return (
    <BaseNodeLayout
      id={id}
      title="On Message"
      icon={<MessageSquare className="h-4 w-4 text-green-600" />}
      headerBackground="bg-gray-50"
      borderColor="border-gray-100"
      handles={{ right: true }}
    >
      <div className="text-sm text-gray-600">
        <p>This flow will be triggered whenever a client sends <strong>any message</strong> to your bot.</p>
        <p className="mt-2 text-xs text-gray-500">Use this as the starting point for general conversations.</p>
      </div>
    </BaseNodeLayout>
  );
};

export default IncomingMessageNode;
