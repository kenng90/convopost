import { Button } from '@/components/ui/button';
import { useFlowActions } from '@/hooks/useFlowActions';
import { Globe } from 'lucide-react';

interface WebSectionProps {
  searchQuery: string;
}

export const WebSection = ({ searchQuery }: WebSectionProps) => {
  const { createNodeHTTP } = useFlowActions();

  const webOptions = [
    {
      icon: Globe,
      label: 'HTTP Request',
      bgColor: 'bg-sky-100',
      textColor: 'text-sky-600',
      onClick: () => createNodeHTTP({ x: 0, y: 0 }),
    },
  ];

  const filteredOptions = webOptions.filter((option) =>
    option.label.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (filteredOptions.length === 0) return null;

  return (
    <div className="grid gap-2">
      {filteredOptions.map((option, index) => (
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
