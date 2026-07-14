import { Button } from '@/components/ui/button';
import { useFlowActions } from '@/hooks/useFlowActions';
import { Users, UserPlus, Route } from 'lucide-react';

interface TeamSectionProps {
  searchQuery: string;
}

export const TeamSection = ({ searchQuery }: TeamSectionProps) => {
  const { createNodeAssignAgent, createNodeAssignGroup, createNodeAssignJourneyStage } = useFlowActions();

  const options = [
    {
      icon: UserPlus,
      label: 'Assign to Agent',
      bgColor: 'bg-blue-100',
      textColor: 'text-blue-600',
      onClick: () => createNodeAssignAgent({ x: 0, y: 0 }),
    },
    {
      icon: Users,
      label: 'Assign to Group',
      bgColor: 'bg-green-100',
      textColor: 'text-green-600',
      onClick: () => createNodeAssignGroup({ x: 0, y: 0 }),
    },
    {
      icon: Route,
      label: 'Move to Journey Stage',
      bgColor: 'bg-purple-100',
      textColor: 'text-purple-700',
      onClick: () => createNodeAssignJourneyStage({ x: 0, y: 0 }),
    },
  ];

  const filtered = options.filter((option) =>
    option.label.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (filtered.length === 0) {
    return null;
  }

  return (
    <div className="grid gap-2">
      {filtered.map((option, index) => (
        <Button
          key={index}
          variant="ghost"
          className="w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none"
          onClick={option.onClick}
        >
          <div className={`${option.bgColor} p-2 rounded-lg mr-3`}>
            <option.icon className={`h-5 w-5 ${option.textColor}`} />
          </div>
          {option.label}
        </Button>
      ))}
    </div>
  );
};
