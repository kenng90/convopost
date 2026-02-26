import React, { useState, useMemo } from 'react';
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Variable } from "lucide-react";
import {
  Command,
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { variables } from '@/data/variablesData';
import { useReactFlow } from '@xyflow/react';

interface VariableTextAreaProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
}

export function VariableTextArea({ value, onChange, placeholder }: VariableTextAreaProps) {
  const [open, setOpen] = useState(false);
  const { getNodes } = useReactFlow();

  // Extract variables from flow nodes
  const flowVariables = useMemo(() => {
    const nodes = getNodes();
    const dynamicVariables: Array<{ label: string; value: string; category: string }> = [];

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

  const insertVariable = (variable: string) => {
    const cursorPosition = (document.activeElement as HTMLTextAreaElement)?.selectionStart || value.length;
    const newValue = value.slice(0, cursorPosition) + `{{${variable}}}` + value.slice(cursorPosition);
    onChange(newValue);
    setOpen(false);
  };

  // Group variables by category
  const groupedVariables = allVariables.reduce((acc, variable) => {
    const category = variable.category || 'Other';
    if (!acc[category]) {
      acc[category] = [];
    }
    acc[category].push(variable);
    return acc;
  }, {} as Record<string, typeof allVariables>);

  return (
    <div className="space-y-2">
      <Textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="min-h-[100px]"
      />
      <div className="flex justify-end">
        <Button
          variant="outline"
          size="sm"
          onClick={() => setOpen(true)}
          className="gap-2"
        >
          <Variable className="h-4 w-4" />
          Variables
        </Button>
      </div>

      <CommandDialog open={open} onOpenChange={setOpen}>
        <Command>
          <CommandInput placeholder="Search variables..." />
          <CommandList>
            <CommandEmpty>No variables found.</CommandEmpty>
            {Object.entries(groupedVariables).map(([category, categoryVariables]) => (
              <CommandGroup key={category} heading={category}>
                {categoryVariables.map((variable) => (
                  <CommandItem
                    key={variable.value}
                    onSelect={() => insertVariable(variable.value)}
                  >
                    {variable.label}
                  </CommandItem>
                ))}
              </CommandGroup>
            ))}
          </CommandList>
        </Command>
      </CommandDialog>
    </div>
  );
}
