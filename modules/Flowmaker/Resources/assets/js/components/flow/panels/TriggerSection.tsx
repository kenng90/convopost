
import { Button } from "@/components/ui/button";
import { useFlowActions } from "@/hooks/useFlowActions";
import { MessageSquare, KeyRound } from "lucide-react";

interface TriggerSectionProps {
  searchQuery: string;
}

export const TriggerSection = ({ searchQuery }: TriggerSectionProps) => {
  const { createNodeIncomingMessage, createNodeKeyword } = useFlowActions();

  const triggers = [
    {
      icon: MessageSquare,
      label: "On Message",
      description: "Trigger on any incoming message",
      bgColor: "bg-green-100",
      textColor: "text-green-600",
      onClick: () => createNodeIncomingMessage({ x: 0, y: 0 }),
    },
    {
      icon: KeyRound,
      label: "Keyword",
      description: "Branch flow based on specific keywords",
      bgColor: "bg-blue-100",
      textColor: "text-blue-600",
      onClick: () => createNodeKeyword({ x: 0, y: 0 }),
    },
  ];

  const filteredTriggers = triggers.filter(trigger =>
    trigger.label.toLowerCase().includes(searchQuery.toLowerCase()) ||
    trigger.description.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (filteredTriggers.length === 0) return null;

  return (
    <div className="grid gap-2">
      {filteredTriggers.map((trigger, index) => (
        <Button
          key={index}
          variant="ghost"
          className="w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none p-2"
          onClick={trigger.onClick}
        >
          <div className={`${trigger.bgColor} p-2 rounded-lg mr-3`}>
            <trigger.icon className={`h-5 w-5 ${trigger.textColor}`} />
          </div>
          <div className="text-left">
            <div>{trigger.label}</div>
            <div className="text-xs text-gray-500 mt-1">{trigger.description}</div>
          </div>
        </Button>
      ))}
    </div>
  );
};
