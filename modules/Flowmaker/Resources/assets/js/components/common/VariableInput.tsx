import React, { useState } from 'react';
import { Input } from "@/components/ui/input";
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
import { useFlowVariables } from '@/hooks/useFlowVariables';

interface VariableInputProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  id?: string;
  className?: string; // Add className prop
}

export function VariableInput({ value, onChange, placeholder, id, className }: VariableInputProps) {
  const [open, setOpen] = useState(false);
  const { groupedVariables } = useFlowVariables();

  const insertVariable = (variable: string) => {
    const newValue = value + `{{${variable}}}`;
    onChange(newValue);
    setOpen(false);
  };

  return (
    <div className={`flex gap-2 items-center w-full ${className || ''}`}>
      <Input
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full"
      />
      <Button
        variant="outline"
        size="icon"
        onClick={() => setOpen(true)}
        type="button"
      >
        <Variable className="h-4 w-4" />
      </Button>

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
