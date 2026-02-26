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