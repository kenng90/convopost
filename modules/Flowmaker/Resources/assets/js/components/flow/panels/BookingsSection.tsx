import { CalendarCheck, CalendarDays, Link2, Settings2, Ticket, TicketX, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useFlowActions } from '@/hooks/useFlowActions';
import { NodeData } from '@/types/flow';

interface BookingsSectionProps {
  searchQuery: string;
}

declare global {
  interface Window {
    data?: {
      planPlugins?: { reminders?: boolean; whatsappcatalog?: boolean };
      bookingSetupUrls?: { overview?: string; services?: string; events?: string; settings?: string };
    };
  }
}

export const BookingsSection = ({ searchQuery }: BookingsSectionProps) => {
  const actions = useFlowActions();
  const planPlugins = window.data?.planPlugins ?? {};
  const setupUrls = window.data?.bookingSetupUrls ?? {};
  const remindersEnabled = planPlugins.reminders !== false;

  const options = [
    {
      icon: CalendarCheck,
      label: 'Book appointment',
      bgColor: 'bg-violet-100',
      textColor: 'text-violet-700',
      requiresReminders: true,
      onClick: () => {
        const data: NodeData = {
          label: 'Book appointment',
          type: 'book_appointment',
          settings: {
            intake_mode: 'lists',
            source_name: '',
            duration_minutes: '',
            header: 'Book appointment',
            body: 'Let us find a time that works for you.',
            footer: '',
            buttonText: 'Choose option',
            duration_header: 'Duration',
            duration_body: 'How long should this appointment be?',
            date_header: 'Select date',
            date_body: 'Pick a day for your appointment.',
            slot_header: 'Select time',
            slot_body: 'Choose an available time slot.',
            success_message: 'Your appointment for {{booking_service}} on {{booking_date}} at {{booking_time}} is confirmed.',
            formFieldMap: {
              serviceField: 'select_3',
              dateField: 'date_4',
              slotField: 'slot',
            },
            serviceOptionMap: {},
            allow_payment_retry: true,
            allow_pay_at_venue: false,
            booking_webhook_url: '',
          },
        };
        return actions.createNodeBase('book_appointment', { x: 250, y: 100 }, data);
      },
    },
    {
      icon: Users,
      label: 'Event registration (list + register)',
      bgColor: 'bg-sky-100',
      textColor: 'text-sky-700',
      requiresReminders: true,
      onClick: () => actions.createEventRegistrationPreset(),
    },
    {
      icon: CalendarDays,
      label: 'List events',
      bgColor: 'bg-sky-100',
      textColor: 'text-sky-700',
      requiresReminders: true,
      onClick: () => {
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
        return actions.createNodeBase('booking_events_list', { x: 250, y: 100 }, data);
      },
    },
    {
      icon: Ticket,
      label: 'Register for event',
      bgColor: 'bg-emerald-100',
      textColor: 'text-emerald-700',
      requiresReminders: true,
      onClick: () => {
        const data: NodeData = {
          label: 'Register for event',
          type: 'booking_event_register',
          settings: {
            occurrence_id: '',
            party_size: '1',
            intake_mode: 'lists',
            success_message: 'You are registered for {{booking_event_title}} on {{booking_event_date}} at {{booking_event_time}}.',
            formFieldMap: {
              occurrenceField: 'occurrence_id',
              partySizeField: 'party_size',
            },
            booking_webhook_url: '',
          },
        };
        return actions.createNodeBase('booking_event_register', { x: 250, y: 100 }, data);
      },
    },
    {
      icon: Link2,
      label: 'Send booking link',
      bgColor: 'bg-indigo-100',
      textColor: 'text-indigo-700',
      requiresReminders: true,
      onClick: () => actions.createNodeBase('send_booking_link', { x: 250, y: 100 }, {
        label: 'Send booking link',
        type: 'send_booking_link',
        settings: {
          link_type: 'appointments',
          message: 'Book online: {{booking_link}}',
        },
      }),
    },
    {
      icon: Settings2,
      label: 'Manage booking',
      bgColor: 'bg-orange-100',
      textColor: 'text-orange-700',
      requiresReminders: true,
      onClick: () => actions.createNodeBase('manage_booking', { x: 250, y: 100 }),
    },
    {
      icon: TicketX,
      label: 'Manage event registration',
      bgColor: 'bg-rose-100',
      textColor: 'text-rose-700',
      requiresReminders: true,
      onClick: () => actions.createNodeBase('manage_event_registration', { x: 250, y: 100 }, {
        label: 'Manage event registration',
        type: 'manage_event_registration',
        settings: {
          reference_variable: 'booking_event_reference',
          default_action: 'cancel',
          success_message: 'Your event registration has been cancelled.',
        },
      }),
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
      {!remindersEnabled && (
        <div className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md p-2">
          Bookings requires the Reminders plugin on your plan.
        </div>
      )}

      {setupUrls.services && (
        <div className="text-[10px] text-gray-500 px-1 pb-1 leading-relaxed">
          Setup:{' '}
          <a href={setupUrls.services} className="text-violet-600 underline" target="_blank" rel="noreferrer">Services</a>
          {' · '}
          <a href={setupUrls.events} className="text-violet-600 underline" target="_blank" rel="noreferrer">Events</a>
          {' · '}
          <a href={setupUrls.settings} className="text-violet-600 underline" target="_blank" rel="noreferrer">Public links</a>
        </div>
      )}

      {filtered.map((option, index) => {
        const disabled =
          (option.requiresReminders && !remindersEnabled) ||
          (option.requiresCatalog && !planPlugins.whatsappcatalog);

        return (
          <Button
            key={index}
            variant="ghost"
            disabled={disabled}
            className="w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none disabled:opacity-50"
            onClick={option.onClick}
          >
            <div className={`${option.bgColor} p-2 rounded-lg mr-3`}>
              <option.icon className={`h-5 w-5 ${option.textColor}`} />
            </div>
            {option.label}
          </Button>
        );
      })}
    </div>
  );
};
