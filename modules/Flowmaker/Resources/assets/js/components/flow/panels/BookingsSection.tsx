import { CalendarCheck, CalendarDays, Ticket } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useFlowActions } from '@/hooks/useFlowActions';
import { NodeData } from '@/types/flow';

interface BookingsSectionProps {
  searchQuery: string;
}

export const BookingsSection = ({ searchQuery }: BookingsSectionProps) => {
  const actions = useFlowActions();

  const options = [
    {
      icon: CalendarCheck,
      label: 'Book appointment',
      bgColor: 'bg-violet-100',
      textColor: 'text-violet-700',
      onClick: () => {
        const position = { x: 250, y: 100 };
        const data: NodeData = {
          label: 'Book appointment',
          type: 'book_appointment',
          settings: {
            source_name: '',
            duration_minutes: '',
            header: 'Book appointment',
            body: 'Let us find a time that works for you.',
            footer: '',
            buttonText: 'Choose option',
            success_message: 'Your appointment for {{booking_service}} on {{booking_date}} at {{booking_time}} is confirmed.',
          },
        };

        return actions.createNodeBase('book_appointment', position, data);
      },
    },
    {
      icon: CalendarDays,
      label: 'List events',
      bgColor: 'bg-sky-100',
      textColor: 'text-sky-700',
      onClick: () => {
        const position = { x: 250, y: 100 };
        const data: NodeData = {
          label: 'List events',
          type: 'booking_events_list',
          settings: {
            header: 'Upcoming events',
            body: 'Choose an event session to register.',
            footer: '',
            buttonText: 'View events',
            limit: '10',
          },
        };

        return actions.createNodeBase('booking_events_list', position, data);
      },
    },
    {
      icon: Ticket,
      label: 'Register for event',
      bgColor: 'bg-emerald-100',
      textColor: 'text-emerald-700',
      onClick: () => {
        const position = { x: 250, y: 100 };
        const data: NodeData = {
          label: 'Register for event',
          type: 'booking_event_register',
          settings: {
            occurrence_id: '',
            party_size: '1',
            success_message: 'You are registered for {{booking_event_title}} on {{booking_event_date}} at {{booking_event_time}}.',
          },
        };

        return actions.createNodeBase('booking_event_register', position, data);
      },
    },
  ];

  const filtered = options.filter(option =>
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
