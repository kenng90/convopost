/**
 * OpenAI Realtime tool definitions for voice booking.
 */

export function isVoiceBookingEnabled(payload) {
  return Boolean(payload?.voice_booking?.enabled);
}

export function buildVoiceBookingTools(payload) {
  if (!isVoiceBookingEnabled(payload)) {
    return [];
  }

  const tools = [];
  const { appointments, events } = payload.voice_booking;

  if (appointments) {
    tools.push(
      {
        type: 'function',
        name: 'list_bookable_services',
        description: 'List appointment services the business offers for booking.',
        parameters: { type: 'object', properties: {}, additionalProperties: false },
      },
      {
        type: 'function',
        name: 'get_available_dates',
        description: 'Get dates with availability for a service in the next booking window.',
        parameters: {
          type: 'object',
          properties: {
            service_name: { type: 'string', description: 'Exact service name from list_bookable_services' },
            duration_minutes: { type: 'integer', description: 'Optional appointment length in minutes' },
          },
          required: ['service_name'],
          additionalProperties: false,
        },
      },
      {
        type: 'function',
        name: 'get_available_slots',
        description: 'Get time slots for a service on a specific date.',
        parameters: {
          type: 'object',
          properties: {
            service_name: { type: 'string' },
            date: { type: 'string', description: 'Date in YYYY-MM-DD format' },
            duration_minutes: { type: 'integer' },
          },
          required: ['service_name', 'date'],
          additionalProperties: false,
        },
      },
      {
        type: 'function',
        name: 'create_appointment_booking',
        description: 'Book an appointment after confirming service, slot, and caller name.',
        parameters: {
          type: 'object',
          properties: {
            service_name: { type: 'string' },
            slot_id: { type: 'string', description: 'Slot id from get_available_slots' },
            customer_name: { type: 'string' },
            phone: { type: 'string', description: 'Caller WhatsApp phone if known' },
            duration_minutes: { type: 'integer' },
          },
          required: ['service_name', 'slot_id', 'customer_name'],
          additionalProperties: false,
        },
      },
    );
  }

  if (events) {
    tools.push(
      {
        type: 'function',
        name: 'list_upcoming_events',
        description: 'List upcoming events open for registration.',
        parameters: { type: 'object', properties: {}, additionalProperties: false },
      },
      {
        type: 'function',
        name: 'get_event_details',
        description: 'Get details for a specific event occurrence.',
        parameters: {
          type: 'object',
          properties: {
            occurrence_id: { type: 'integer' },
          },
          required: ['occurrence_id'],
          additionalProperties: false,
        },
      },
      {
        type: 'function',
        name: 'create_event_registration',
        description: 'Register the caller for an event after confirming details.',
        parameters: {
          type: 'object',
          properties: {
            occurrence_id: { type: 'integer' },
            customer_name: { type: 'string' },
            phone: { type: 'string' },
            party_size: { type: 'integer', minimum: 1 },
          },
          required: ['occurrence_id', 'customer_name'],
          additionalProperties: false,
        },
      },
    );
  }

  return tools;
}

export function voiceBookingInstructionLines(payload) {
  if (!isVoiceBookingEnabled(payload)) {
    return [];
  }

  return [
    'You can book appointments and events using the provided tools.',
    'Always use tools to check availability — never guess dates or times.',
    'Confirm service/event, date/time, and caller name before calling create_appointment_booking or create_event_registration.',
    'For paid services, tell the caller a WhatsApp payment link will be sent after booking.',
  ];
}
