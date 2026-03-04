
import { Button } from "@/components/ui/button";
import { useFlowActions } from "@/hooks/useFlowActions";
import { GitMerge, Clock, Square, Calculator, CreditCard } from "lucide-react";

interface FlowControlSectionProps {
  searchQuery: string;
}

export const FlowControlSection = ({ searchQuery }: FlowControlSectionProps) => {
  const { createNodeBase } = useFlowActions();

  const logicControls = [
    {
      icon: GitMerge,
      label: "Branch",
      bgColor: "bg-violet-100",
      textColor: "text-violet-600",
      onClick: () => createNodeBase('branch', { x: 0, y: 0 }),
    },
    {
      icon: Calculator,
      label: "Counter",
      bgColor: "bg-orange-100",
      textColor: "text-orange-600",
      onClick: () => createNodeBase('counter', { x: 0, y: 0 }, {
        settings: {
          counter: {
            maxExecutions: 1,
            period: 'all_time'
          }
        }
      }),
    },
    {
      icon: CreditCard,
      label: "Check User Pricing",
      bgColor: "bg-green-100",
      textColor: "text-green-600",
      onClick: () => createNodeBase('check_pricing', { x: 0, y: 0 }, {
        settings: {
          pricing: {
            freeExecutions: 0
          }
        }
      }),
    },
    {
      icon: Square,
      label: "End",
      bgColor: "bg-red-100",
      textColor: "text-red-600",
      onClick: () => createNodeBase('end', { x: 0, y: 0 }, {
        label: "End Flow"
      }),
    },
  ];

  const filteredControls = logicControls.filter(control =>
    control.label.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (filteredControls.length === 0) return null;

  return (
    <div className="grid gap-2">
      {filteredControls.map((control, index) => (
        <Button
          key={index}
          variant="ghost"
          className="w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none"
          onClick={control.onClick}
        >
          <div className={`${control.bgColor} p-2 rounded-lg mr-3`}>
            <control.icon className={`h-5 w-5 ${control.textColor}`} />
          </div>
          {control.label}
        </Button>
      ))}
    </div>
  );
};
