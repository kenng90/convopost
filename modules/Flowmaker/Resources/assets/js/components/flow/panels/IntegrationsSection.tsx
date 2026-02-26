
import { useFlowActions } from "@/hooks/useFlowActions";
import { Button } from "@/components/ui/button";
import { BrainCircuit, Atom, BrainCog, Sparkles } from "lucide-react";

interface IntegrationsSectionProps {
  searchQuery: string;
}

export const IntegrationsSection = ({ searchQuery }: IntegrationsSectionProps) => {
  const { createNodeOpenAI } = useFlowActions();

  const integrations = [
    {
      name: "LLM",
      icon: BrainCog,
      bgColor: "bg-black",
      textColor: "text-white",
      borderColor: "rainbow-border",
      onClick: () => createNodeOpenAI({ x: 0, y: 0 }),
    },
  ];

  const filteredIntegrations = integrations.filter((integration) =>
    integration.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="grid gap-2">
      {filteredIntegrations.map((integration) => (
        <Button
          key={integration.name}
          variant="ghost"
          className="w-full justify-start text-gray-200 hover:text-white focus:ring-0 focus-visible:ring-0 focus:outline-none group relative overflow-hidden"
          onClick={integration.onClick}
        >
          <div className={`${integration.bgColor} p-2 rounded-lg mr-3 relative overflow-hidden group-hover:animate-pulse rainbow-border`}>
            <integration.icon className={`h-5 w-5 ${integration.textColor} relative z-10`} />
            <div className="absolute inset-0 bg-gradient-to-r from-ai-purple/30 via-ai-vivid/30 to-ai-magenta/30 opacity-0 group-hover:opacity-100 transition-opacity"></div>
          </div>
          <span className="relative" style={{ color: "#000000" }}>
            {integration.name}
          </span>
          <div className="absolute inset-0 bg-gradient-to-r from-ai-purple/5 to-ai-magenta/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
        </Button>
      ))}
    </div>
  );
};
