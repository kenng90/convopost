import { Variable, Server } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { useState } from "react";
import { useReactFlow } from "@xyflow/react";
import { WebhookVariable, NodeData } from "@/types/flow";
import { useFlowActions } from "@/hooks/useFlowActions";

interface VariablesSectionProps {
  searchQuery: string;
}

export const VariablesSection = ({ searchQuery }: VariablesSectionProps) => {
  const { getNodes, setNodes } = useReactFlow();
  const { createNodeHTTP } = useFlowActions();
  const [isOpen, setIsOpen] = useState(false);
  const [newVariableName, setNewVariableName] = useState("");
  const [newVariableRequired, setNewVariableRequired] = useState(true);

  const items = [
    { 
      icon: Variable, 
      label: "Variables", 
      onClick: () => setIsOpen(true),
      showDialog: true 
    },
    { 
      icon: Server, 
      label: "HTTP Request", 
      onClick: () => {
        const position = { x: 0, y: 0 }; // Position will be adjusted by useFlowActions
        createNodeHTTP(position);
      },
      showDialog: false
    },
  ];

  const filteredItems = items.filter((item) =>
    item.label.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const webhookNode = getNodes().find((node) => node.type === 'webhook');
  const variables = (webhookNode?.data as NodeData)?.settings?.webhook?.variables || [];

  const addVariable = () => {
    if (!newVariableName.trim()) return;

    const newVariable: WebhookVariable = {
      id: Math.random().toString(36).substring(7),
      name: newVariableName.trim(),
      type: "string",
      required: newVariableRequired,
    };

    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.type === 'webhook') {
          return {
            ...node,
            data: {
              ...node.data as NodeData,
              settings: {
                ...(node.data as NodeData).settings,
                webhook: {
                  ...(node.data as NodeData).settings?.webhook,
                  variables: [...variables, newVariable],
                },
              },
            },
          };
        }
        return node;
      })
    );

    setNewVariableName("");
    setNewVariableRequired(true);
  };

  const updateVariable = (variableId: string, updates: Partial<WebhookVariable>) => {
    setNodes((nodes) =>
      nodes.map((node) => {
        if (node.type === 'webhook') {
          const updatedVariables = variables.map((v) =>
            v.id === variableId ? { ...v, ...updates } : v
          );
          return {
            ...node,
            data: {
              ...node.data as NodeData,
              settings: {
                ...(node.data as NodeData).settings,
                webhook: {
                  ...(node.data as NodeData).settings?.webhook,
                  variables: updatedVariables,
                },
              },
            },
          };
        }
        return node;
      })
    );
  };

  if (filteredItems.length === 0) return null;

  return (
    <div className="space-y-4">
      <h3 className="font-medium text-sm text-gray-900">Variables</h3>
      <div className="grid gap-2">
        {filteredItems.map((item) => (
          <Dialog key={item.label} open={item.showDialog ? isOpen : false} onOpenChange={setIsOpen}>
            {item.showDialog ? (
              <DialogTrigger asChild>
                <Button
                  variant="outline"
                  className="justify-start w-full"
                  onClick={item.onClick}
                >
                  <item.icon className="mr-2 h-4 w-4" />
                  {item.label}
                </Button>
              </DialogTrigger>
            ) : (
              <Button
                variant="outline"
                className="justify-start w-full"
                onClick={item.onClick}
              >
                <item.icon className="mr-2 h-4 w-4" />
                {item.label}
              </Button>
            )}
            {item.showDialog && (
              <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                  <DialogTitle>Manage Variables</DialogTitle>
                </DialogHeader>
                <div className="space-y-4 py-4">
                  <div className="space-y-4">
                    <div className="space-y-2">
                      <Label>Add New Variable</Label>
                      <div className="flex items-start gap-2">
                        <Input
                          placeholder="Variable name"
                          value={newVariableName}
                          onChange={(e) => setNewVariableName(e.target.value)}
                          className="flex-1"
                        />
                        <div className="flex items-center gap-2">
                          <Switch
                            checked={newVariableRequired}
                            onCheckedChange={setNewVariableRequired}
                          />
                          <Label>Required</Label>
                        </div>
                      </div>
                      <Button 
                        onClick={addVariable} 
                        variant="outline" 
                        size="sm"
                        className="w-full mt-2"
                      >
                        Add Variable
                      </Button>
                    </div>

                    <div className="space-y-2">
                      <Label>Existing Variables</Label>
                      {variables.map((variable) => (
                        <div key={variable.id} className="flex items-start gap-2">
                          <Input
                            placeholder="Variable name"
                            value={variable.name}
                            onChange={(e) =>
                              updateVariable(variable.id, { name: e.target.value })
                            }
                            className="flex-1"
                          />
                          <div className="flex items-center gap-2">
                            <Switch
                              checked={variable.required}
                              onCheckedChange={(checked) =>
                                updateVariable(variable.id, { required: checked })
                              }
                            />
                            <Label>Required</Label>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </DialogContent>
            )}
          </Dialog>
        ))}
      </div>
    </div>
  );
};