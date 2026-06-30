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
      
      if (node.type === 'openai' && settings.llm?.variableName) {
        dynamicVariables.push({
          label: `Full AI Response for ${settings.llm.variableName}`,
          value: settings.llm.variableName,
          category: 'Flow Variables'
        },{
          label: `AI Response  for ${settings.llm.variableName}`,
          value: settings.llm.variableName + '_message',
          category: 'Flow Variables'
        },{
          label: `Detected Intent for ${settings.llm.variableName}`,
          value: settings.llm.variableName + '_intent',
          category: 'Flow Variables'
        });
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