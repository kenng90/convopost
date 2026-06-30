import { useMemo } from 'react';
import { useReactFlow } from '@xyflow/react';
import { variables } from '@/data/variablesData';

type Variable = {
  label: string;
  value: string;
  category: string;
};

export function useFlowVariables() {
  const { getNodes } = useReactFlow();

  // Extract variables from flow nodes
  const flowVariables = useMemo(() => {
    const nodes = getNodes();
    const dynamicVariables: Variable[] = [];

    nodes.forEach(node => {
      const nodeData = node.data as any;
      const settings = nodeData?.settings || {};
      
      if (node.type === 'openai') {
        const llmSettings = settings.llm || settings.openai;
        if (llmSettings?.variableName) {
          dynamicVariables.push({
            label: `Full AI Response for ${llmSettings.variableName}`,
            value: llmSettings.variableName,
            category: 'Flow Variables'
          },{
            label: `AI Response  for ${llmSettings.variableName}`,
            value: llmSettings.variableName + '_message',
            category: 'Flow Variables'
          },{
            label: `Detected Intent for ${llmSettings.variableName}`,
            value: llmSettings.variableName + '_intent',
            category: 'Flow Variables'
          });
        }
      }
      
      if (node.type === 'question' && settings.variableName) {
        dynamicVariables.push({
          label: `User Answer (${nodeData.label || 'Question'})`,
          value: settings.variableName,
          category: 'Flow Variables'
        });
      }
      
      if (node.type === 'http' && settings.http?.responseVar) {
        dynamicVariables.push({
          label: `HTTP Response (${nodeData.label || 'HTTP Request'})`,
          value: settings.http.responseVar,
          category: 'Flow Variables'
        });
      }
      
      if (node.type === 'datastore' && settings.variableName) {
        dynamicVariables.push({
          label: `Data Store (${nodeData.label || 'Data Store'})`,
          value: settings.variableName,
          category: 'Flow Variables'
        });
      }

      if (node.type === 'book_appointment') {
        const label = nodeData.label || 'Book appointment';
        dynamicVariables.push(
          { label: `${label} — service`, value: 'booking_service', category: 'Flow Variables' },
          { label: `${label} — date`, value: 'booking_date', category: 'Flow Variables' },
          { label: `${label} — time`, value: 'booking_time', category: 'Flow Variables' },
          { label: `${label} — reference`, value: 'booking_reference', category: 'Flow Variables' },
        );
      }

      if (node.type === 'booking_event_register') {
        const label = nodeData.label || 'Register for event';
        dynamicVariables.push(
          { label: `${label} — event title`, value: 'booking_event_title', category: 'Flow Variables' },
          { label: `${label} — event date`, value: 'booking_event_date', category: 'Flow Variables' },
          { label: `${label} — event time`, value: 'booking_event_time', category: 'Flow Variables' },
          { label: `${label} — reference`, value: 'booking_event_reference', category: 'Flow Variables' },
        );
      }

      if (node.type === 'send_booking_link') {
        dynamicVariables.push(
          { label: 'Booking share URL', value: 'booking_share_url', category: 'Flow Variables' },
        );
      }

      if (node.type === 'listing_inquiry') {
        const prefix = (settings.bookingVariablePrefix || 'listing_booking').replace(/[^a-zA-Z0-9_]/g, '') || 'listing_booking';
        const label = nodeData.label || 'Send Listings Link';

        dynamicVariables.push(
          {
            label: `${label} — item title`,
            value: `${prefix}_item_title`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — customer name`,
            value: `${prefix}_customer_name`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — customer phone`,
            value: `${prefix}_customer_phone`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — preferred date/time`,
            value: `${prefix}_preferred_datetime`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — notes`,
            value: `${prefix}_notes`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — booking message`,
            value: `${prefix}_message`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — reservation id`,
            value: `${prefix}_reservation_id`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — selected listing (legacy)`,
            value: 'selected_listing',
            category: 'Flow Variables'
          }
        );
      }

      if (node.type === 'whatsapp_catalog') {
        const prefix = (settings.checkoutVariablePrefix || 'catalog_order').replace(/[^a-zA-Z0-9_]/g, '') || 'catalog_order';
        const label = nodeData.label || 'Send Catalog Link';

        dynamicVariables.push(
          {
            label: `${label} — items`,
            value: `${prefix}_items`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — total`,
            value: `${prefix}_total`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — item count`,
            value: `${prefix}_item_count`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — order message`,
            value: `${prefix}_message`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — JSON`,
            value: `${prefix}_json`,
            category: 'Flow Variables'
          },
          {
            label: `${label} — cart (legacy)`,
            value: 'catalog_cart',
            category: 'Flow Variables'
          }
        );
      }
    });

    return dynamicVariables;
  }, [getNodes]);

  // Combine static and dynamic variables
  const allVariables = useMemo(() => {
    return [...variables, ...flowVariables];
  }, [flowVariables]);

  // Group variables by category
  const groupedVariables = useMemo(() => {
    return allVariables.reduce((acc, variable) => {
      const category = variable.category || 'Other';
      if (!acc[category]) {
        acc[category] = [];
      }
      acc[category].push(variable);
      return acc;
    }, {} as Record<string, Variable[]>);
  }, [allVariables]);

  return {
    allVariables,
    groupedVariables,
    flowVariables
  };
} 