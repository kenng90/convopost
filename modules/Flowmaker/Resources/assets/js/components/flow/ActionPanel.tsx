import {
  MessageCircle,
  Zap,
  GitMerge,
  BrainCog,
  Database,
  Globe,
  CalendarCheck,
  ShoppingBag,
  UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import { TriggerSection } from './panels/TriggerSection';
import { MessagingSection } from './panels/MessagingSection';
import { BookingsSection } from './panels/BookingsSection';
import { SellSection } from './panels/SellSection';
import { TeamSection } from './panels/TeamSection';
import { FlowControlSection } from './panels/FlowControlSection';
import { IntegrationsSection } from './panels/IntegrationsSection';
import { DataSection } from './panels/DataSection';
import { WebSection } from './panels/WebSection';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

export type BuilderOutcomeMode = 'all' | 'sell' | 'book' | 'support' | 'lead';

interface ActionPanelProps {
  onImportClick: () => void;
  onPopoverIndexChange?: (index: number | null) => void;
  onOpenDataSidebar: () => void;
  outcomeMode?: BuilderOutcomeMode;
  onOutcomeModeChange?: (mode: BuilderOutcomeMode) => void;
}

const MODE_VISIBLE: Record<BuilderOutcomeMode, string[]> = {
  all: ['Events', 'Message', 'Sell', 'Book', 'Logic', 'Team', 'AI', 'Data', 'Connect'],
  sell: ['Events', 'Message', 'Sell', 'Logic', 'Team', 'Connect'],
  book: ['Events', 'Message', 'Book', 'Logic', 'Team', 'Connect'],
  support: ['Events', 'Message', 'Logic', 'Team', 'AI', 'Data', 'Connect'],
  lead: ['Events', 'Message', 'Logic', 'Team', 'AI', 'Connect'],
};

const ActionPanel = ({
  onImportClick,
  onPopoverIndexChange,
  onOpenDataSidebar,
  outcomeMode = 'all',
  onOutcomeModeChange,
}: ActionPanelProps) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [openPopoverIndex, setOpenPopoverIndex] = useState<number | null>(null);

  const handlePopoverChange = (index: number | null) => {
    setOpenPopoverIndex(index);
    if (onPopoverIndexChange) {
      onPopoverIndexChange(index);
    }
  };

  const handleMouseEnter = (index: number) => {
    handlePopoverChange(index);
  };

  const handleMouseLeave = () => {
    handlePopoverChange(null);
  };

  const handleDataActionClick = () => {
    onOpenDataSidebar();
  };

  const actions = [
    { icon: Zap, label: 'Events', content: <TriggerSection searchQuery={searchQuery} /> },
    { icon: MessageCircle, label: 'Message', content: <MessagingSection searchQuery={searchQuery} /> },
    { icon: ShoppingBag, label: 'Sell', content: <SellSection searchQuery={searchQuery} /> },
    { icon: CalendarCheck, label: 'Book', content: <BookingsSection searchQuery={searchQuery} /> },
    { icon: GitMerge, label: 'Logic', content: <FlowControlSection searchQuery={searchQuery} /> },
    { icon: UsersRound, label: 'Team', content: <TeamSection searchQuery={searchQuery} /> },
    { icon: BrainCog, label: 'AI', content: <IntegrationsSection searchQuery={searchQuery} /> },
    {
      icon: Database,
      label: 'Data',
      content: <DataSection searchQuery={searchQuery} onOpenDataSidebar={onOpenDataSidebar} />,
    },
    { icon: Globe, label: 'Connect', content: <WebSection searchQuery={searchQuery} /> },
  ];

  const visibleLabels = MODE_VISIBLE[outcomeMode] ?? MODE_VISIBLE.all;
  const visibleActions = actions.filter((action) => visibleLabels.includes(action.label));

  const firstPanelLabels = ['Events', 'Message', 'Sell', 'Book', 'Logic'];
  const firstPanelActions = visibleActions.filter((a) => firstPanelLabels.includes(a.label));
  const secondPanelActions = visibleActions.filter((a) => !firstPanelLabels.includes(a.label));

  const modes: { id: BuilderOutcomeMode; label: string }[] = [
    { id: 'all', label: 'All' },
    { id: 'sell', label: 'Sell' },
    { id: 'book', label: 'Book' },
    { id: 'support', label: 'Support' },
    { id: 'lead', label: 'Lead' },
  ];

  return (
    <div className="flex flex-col items-center gap-3">
      {onOutcomeModeChange && (
        <div className="bg-white/95 rounded-lg shadow-sm p-2 w-full">
          <div className="flex flex-wrap gap-1 justify-center">
            {modes.map((mode) => (
              <button
                key={mode.id}
                type="button"
                onClick={() => onOutcomeModeChange(mode.id)}
                className={`text-[10px] px-2 py-1 rounded-md font-medium transition-colors ${
                  outcomeMode === mode.id
                    ? 'bg-gray-900 text-white'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                }`}
              >
                {mode.label}
              </button>
            ))}
          </div>
        </div>
      )}

      <div className="bg-white/95 backdrop-blur-sm rounded-lg shadow-sm p-4 w-full">
        <div className="flex flex-col items-center space-y-5">
          {firstPanelActions.map((action, index) => (
            <Popover
              key={action.label}
              open={openPopoverIndex === index}
              onOpenChange={(open) => handlePopoverChange(open ? index : null)}
            >
              <PopoverTrigger asChild>
                <div
                  className="flex flex-col items-center space-y-1 group"
                  onMouseEnter={() => handleMouseEnter(index)}
                  onMouseLeave={handleMouseLeave}
                >
                  <Button variant="ghost" size="icon" className="h-14 w-14 data-[state=open]:bg-accent">
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

      {secondPanelActions.length > 0 && (
        <div className="relative bg-[#121213] backdrop-blur-sm rounded-lg shadow-lg p-4 w-full border border-gray-800 overflow-hidden">
          <div className="relative z-10 flex flex-col items-center space-y-5">
            {secondPanelActions.map((action, index) => {
              const actualIndex = index + firstPanelActions.length;
              return (
                <Popover
                  key={action.label}
                  open={openPopoverIndex === actualIndex}
                  onOpenChange={(open) => handlePopoverChange(open ? actualIndex : null)}
                >
                  <PopoverTrigger asChild>
                    <div
                      className="flex flex-col items-center space-y-1 group"
                      onMouseEnter={() => handleMouseEnter(actualIndex)}
                      onMouseLeave={handleMouseLeave}
                      onClick={action.label === 'Data' ? handleDataActionClick : undefined}
                    >
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-14 w-14 data-[state=open]:bg-gray-800 hover:bg-gray-800/50 transition-all"
                      >
                        <action.icon className="h-8 w-8 text-gray-300 group-hover:text-white transition-colors" />
                      </Button>
                      <span className="text-xs font-medium text-gray-400 group-hover:text-gray-200 transition-colors">
                        {action.label}
                      </span>
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
        </div>
      )}
    </div>
  );
};

export default ActionPanel;
