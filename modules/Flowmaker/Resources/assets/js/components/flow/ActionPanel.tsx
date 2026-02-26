
import { MessageCircle, Zap, GitMerge, BrainCog, Database, Globe } from "lucide-react";
import { useState } from "react";
import { TriggerSection } from "./panels/TriggerSection";
import { MessagingSection } from "./panels/MessagingSection";
import { FlowControlSection } from "./panels/FlowControlSection";
import { IntegrationsSection } from "./panels/IntegrationsSection";
import { DataSection } from "./panels/DataSection";
import { WebSection } from "./panels/WebSection";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

interface ActionPanelProps {
  onImportClick: () => void;
  onPopoverIndexChange?: (index: number | null) => void;
  onOpenDataSidebar: () => void;
}

const ActionPanel = ({
  onImportClick,
  onPopoverIndexChange,
  onOpenDataSidebar
}: ActionPanelProps) => {
  const [searchQuery, setSearchQuery] = useState("");
  const [openPopoverIndex, setOpenPopoverIndex] = useState<number | null>(null);

  const handlePopoverChange = (index: number | null) => {
    setOpenPopoverIndex(index);
    if (onPopoverIndexChange) {
      onPopoverIndexChange(index);
    }
  };

  // Handle mouse enter for actions
  const handleMouseEnter = (index: number) => {
    handlePopoverChange(index);
  };

  // Handle mouse leave for actions
  const handleMouseLeave = () => {
    handlePopoverChange(null);
  };

  // Handle click for Data action
  const handleDataActionClick = () => {
    // When Data action is clicked, open the sidebar
    onOpenDataSidebar();
  };

  const actions = [
    { icon: Zap, label: "Events", content: <TriggerSection searchQuery={searchQuery} /> },
    { icon: MessageCircle, label: "Message", content: <MessagingSection searchQuery={searchQuery} /> },
    { icon: GitMerge, label: "Logic", content: <FlowControlSection searchQuery={searchQuery} /> },
    // Web moved to second panel
    { icon: BrainCog, label: "AI", content: <IntegrationsSection searchQuery={searchQuery} /> },
    { icon: Database, label: "Data", content: <DataSection searchQuery={searchQuery} onOpenDataSidebar={onOpenDataSidebar} /> },
    { icon: Globe, label: "API", content: <WebSection searchQuery={searchQuery} /> },
  ];

  // First panel: Events, Messages, Logic
  const firstPanelActions = actions.slice(0, 3);
  // Second panel: AI, Data, Web
  const secondPanelActions = actions.slice(3);

  return (
    <div className="flex flex-col items-center">
      {/* First panel: Events, Messages, Logic */}
      <div className="bg-white/95 backdrop-blur-sm rounded-lg shadow-sm p-4 w-full">
        <div className="flex flex-col items-center space-y-5">
          {firstPanelActions.map((action, index) => (
            <Popover 
              key={index}
              open={openPopoverIndex === index}
              onOpenChange={(open) => handlePopoverChange(open ? index : null)}
            >
              <PopoverTrigger asChild>
                <div 
                  className="flex flex-col items-center space-y-1 group"
                  onMouseEnter={() => handleMouseEnter(index)}
                  onMouseLeave={handleMouseLeave}
                >
                  <Button 
                    variant="ghost" 
                    size="icon" 
                    className="h-14 w-14 data-[state=open]:bg-accent"
                  >
                    <action.icon className="h-8 w-8 text-gray-700" />
                  </Button>
                  <span className="text-xs font-medium text-gray-600">{action.label}</span>
                </div>
              </PopoverTrigger>
              <PopoverContent 
                className="w-80 p-4 popover-panel" 
                align="center" 
                side="right"
                onMouseEnter={() => handleMouseEnter(index)}
                onMouseLeave={handleMouseLeave}
              >
                {action.content}
              </PopoverContent>
            </Popover>
          ))}
        </div>
      </div>
      
      {/* Second panel: AI, Data, Web with shiny black effect */}
      <div className="relative bg-[#121213] backdrop-blur-sm rounded-lg shadow-lg p-4 w-full mt-4 
                      group animate-pulse-subtle border border-gray-800 
                      before:absolute before:inset-0 before:rounded-lg before:bg-gradient-to-r 
                      before:from-indigo-500/20 before:via-purple-500/20 before:to-pink-500/20 
                      before:opacity-30 before:blur-xl before:animate-gradient-x overflow-hidden">
        <div className="relative z-10 flex flex-col items-center space-y-5">
          {secondPanelActions.map((action, index) => {
            const actualIndex = index + firstPanelActions.length; // Adjust index to match the original array
            return (
              <Popover 
                key={actualIndex}
                open={openPopoverIndex === actualIndex}
                onOpenChange={(open) => handlePopoverChange(open ? actualIndex : null)}
              >
                <PopoverTrigger asChild>
                  <div 
                    className="flex flex-col items-center space-y-1 group"
                    onMouseEnter={() => handleMouseEnter(actualIndex)}
                    onMouseLeave={handleMouseLeave}
                    onClick={action.label === "Data" ? handleDataActionClick : undefined}
                  >
                    <Button 
                      variant="ghost" 
                      size="icon" 
                      className="h-14 w-14 data-[state=open]:bg-gray-800 hover:bg-gray-800/50 transition-all"
                    >
                      <action.icon className="h-8 w-8 text-gray-300 group-hover:text-white transition-colors" />
                    </Button>
                    <span className="text-xs font-medium text-gray-400 group-hover:text-gray-200 transition-colors">{action.label}</span>
                  </div>
                </PopoverTrigger>
                <PopoverContent 
                  className="w-80 p-4 popover-panel" 
                  align="center" 
                  side="right"
                  onMouseEnter={() => handleMouseEnter(actualIndex)}
                  onMouseLeave={handleMouseLeave}
                >
                  {action.content}
                </PopoverContent>
              </Popover>
            );
          })}
        </div>
        <div className="absolute -bottom-1 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500 to-transparent opacity-70"></div>
        <div className="absolute -top-1 left-0 right-0 h-px bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-70"></div>
      </div>
    </div>
  );
};

export default ActionPanel;
