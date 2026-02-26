
import { useFlowActions } from "@/hooks/useFlowActions";
import { Button } from "@/components/ui/button";
import { Database, Bot, Variable, HelpCircle } from "lucide-react";
import { NodeData } from "@/types/flow";
import { useState } from "react";

interface DataSectionProps {
  searchQuery: string;
  onOpenDataSidebar?: () => void;
}

export const DataSection = ({ searchQuery, onOpenDataSidebar }: DataSectionProps) => {
  const { createNodeBase } = useFlowActions();

  const dataActions = [
    {
      name: "Ask Question",
      icon: HelpCircle,
      bgColor: "bg-teal-100",
      textColor: "text-teal-600",
      onClick: () => createNodeBase('question', { x: 0, y: 0 }, {
        label: "Question",
        type: "question",
        settings: {
          question: "",
          variableName: ""
        }
      }),
    },
    {
      name: "Set Variable",
      icon: Variable,
      bgColor: "bg-cyan-100",
      textColor: "text-cyan-600",
      onClick: () => createNodeBase('datastore', { x: 0, y: 0 }, {
        label: "Set Variable",
        type: "datastore",
        settings: {
          dataStore: {
            name: "",
            type: "database",
            connectionDetails: {}
          }
        }
      }),
    },
    {
      name: "AI Bot Training",
      icon: Bot,
      bgColor: "bg-purple-100",
      textColor: "text-purple-600",
      onClick: () => {
        if (onOpenDataSidebar) {
          onOpenDataSidebar();
        }
      },
    },
  ];

  const filteredDataActions = dataActions.filter((action) =>
    action.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (filteredDataActions.length === 0) return null;

  return (
    <div className="grid gap-2">
      {filteredDataActions.map((action) => (
        <Button
          key={action.name}
          variant="ghost"
          className="w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none"
          onClick={action.onClick}
        >
          <div className={`${action.bgColor} p-2 rounded-lg mr-3`}>
            <action.icon className={`h-5 w-5 ${action.textColor}`} />
          </div>
          {action.name}
        </Button>
      ))}
    </div>
  );
};
