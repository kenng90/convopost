import React from 'react';
import { Handle, Position } from '@xyflow/react';
import { List, Plus, X, Trash2 } from 'lucide-react';
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { NodeData } from '@/types/flow';
import { VariableTextArea } from '../common/VariableTextArea';
import { VariableInput } from '../common/VariableInput';
import { useCallback, useState, useEffect } from 'react';
import { useFlowActions } from "@/hooks/useFlowActions";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";

interface ListMessageNodeProps {
  data: NodeData;
  id: string;
  selected?: boolean;
  isConnectable?: boolean;
}

interface ListRow {
  id: string;
  title: string;
  description: string;
}

interface ListSection {
  id: string;
  title: string;
  rows: ListRow[];
}

interface NodeSettings {
  header: string;
  body: string;
  footer: string;
  buttonText: string;
  sections: ListSection[];
}

const ListMessageNode = ({ data, id, selected, isConnectable }: ListMessageNodeProps) => {
  const { deleteNode } = useFlowActions();
  const [nodeSettings, setNodeSettings] = useState<NodeSettings>(() => {
    const initialSettings: NodeSettings = {
      header: data.settings?.header || '',
      body: data.settings?.body || '',
      footer: data.settings?.footer || '',
      buttonText: data.settings?.buttonText || 'Choose an option',
      sections: data.settings?.sections || [
        {
          id: 'section1',
          title: 'Options',
          rows: [
            { id: 'row1', title: 'Option 1', description: 'Description for option 1' }
          ]
        }
      ]
    };
    return initialSettings;
  });

  useEffect(() => {
    if (data.settings) {
      Object.keys(nodeSettings).forEach(key => {
        data.settings[key] = nodeSettings[key];
      });
    }
  }, [nodeSettings, data]);

  const updateNodeData = useCallback((key: string, value: any) => {
    setNodeSettings(prev => ({
      ...prev,
      [key]: value
    }));
  }, []);

  const updateSection = useCallback((sectionId: string, field: string, value: string) => {
    setNodeSettings(prev => ({
      ...prev,
      sections: prev.sections.map(section =>
        section.id === sectionId ? { ...section, [field]: value } : section
      )
    }));
  }, []);

  const updateRow = useCallback((sectionId: string, rowId: string, field: string, value: string) => {
    setNodeSettings(prev => ({
      ...prev,
      sections: prev.sections.map(section =>
        section.id === sectionId
          ? {
              ...section,
              rows: section.rows.map(row =>
                row.id === rowId ? { ...row, [field]: value } : row
              )
            }
          : section
      )
    }));
  }, []);

  const addSection = useCallback(() => {
    const newSection: ListSection = {
      id: `section${Date.now()}`,
      title: 'New Section',
      rows: [
        { id: `row${Date.now()}`, title: 'New Option', description: 'Description' }
      ]
    };
    setNodeSettings(prev => ({
      ...prev,
      sections: [...prev.sections, newSection]
    }));
  }, []);

  const removeSection = useCallback((sectionId: string) => {
    if (nodeSettings.sections.length > 1) {
      setNodeSettings(prev => ({
        ...prev,
        sections: prev.sections.filter(section => section.id !== sectionId)
      }));
    }
  }, [nodeSettings.sections.length]);

  const addRow = useCallback((sectionId: string) => {
    const newRow: ListRow = {
      id: `row${Date.now()}`,
      title: 'New Option',
      description: 'Description'
    };
    setNodeSettings(prev => ({
      ...prev,
      sections: prev.sections.map(section =>
        section.id === sectionId
          ? { ...section, rows: [...section.rows, newRow] }
          : section
      )
    }));
  }, []);

  const removeRow = useCallback((sectionId: string, rowId: string) => {
    setNodeSettings(prev => ({
      ...prev,
      sections: prev.sections.map(section =>
        section.id === sectionId && section.rows.length > 1
          ? { ...section, rows: section.rows.filter(row => row.id !== rowId) }
          : section
      )
    }));
  }, []);

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="bg-white rounded-lg shadow-lg w-[350px] min-h-[650px] flex flex-col">
          <Handle
            type="target"
            position={Position.Left}
            style={{ left: '-4px', background: '#555', zIndex: 50 }}
            isConnectable={isConnectable}
          />

          <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
            <List className="h-4 w-4 text-green-600" />
            <div className="font-medium">List Message</div>
          </div>

          <div className="p-4 space-y-4 flex-1 overflow-y-auto">
            <div className="space-y-2">
              <Label htmlFor={`${id}-header`}>Header</Label>
              <VariableInput
                id={`${id}-header`}
                placeholder="Enter header text"
                value={nodeSettings.header}
                onChange={(value) => updateNodeData('header', value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor={`${id}-body`}>Body</Label>
              <VariableTextArea
                value={nodeSettings.body}
                onChange={(value) => updateNodeData('body', value)}
                placeholder="Enter message body"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor={`${id}-footer`}>Footer</Label>
              <VariableInput
                id={`${id}-footer`}
                placeholder="Enter footer text"
                value={nodeSettings.footer}
                onChange={(value) => updateNodeData('footer', value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor={`${id}-button-text`}>Button Text</Label>
              <VariableInput
                id={`${id}-button-text`}
                placeholder="Choose an option"
                value={nodeSettings.buttonText}
                onChange={(value) => updateNodeData('buttonText', value)}
              />
            </div>

            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <Label>Sections</Label>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={addSection}
                >
                  <Plus className="h-4 w-4 mr-1" />
                  Add Section
                </Button>
              </div>

              {nodeSettings.sections.map((section, sectionIndex) => (
                <div key={section.id} className="border rounded-lg p-3 space-y-3">
                  <div className="flex items-center justify-between">
                    <Label>Section {sectionIndex + 1}</Label>
                    {nodeSettings.sections.length > 1 && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => removeSection(section.id)}
                      >
                        <X className="h-4 w-4" />
                      </Button>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label>Section Title</Label>
                    <VariableInput
                      placeholder="Section title"
                      value={section.title}
                      onChange={(value) => updateSection(section.id, 'title', value)}
                    />
                  </div>

                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <Label>Options</Label>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => addRow(section.id)}
                      >
                        <Plus className="h-4 w-4 mr-1" />
                        Add Option
                      </Button>
                    </div>

                    {section.rows.map((row, rowIndex) => (
                      <div key={row.id} className="relative border rounded p-2 space-y-2">
                        <div className="flex items-center justify-between">
                          <Label>Option {rowIndex + 1}</Label>
                          {section.rows.length > 1 && (
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => removeRow(section.id, row.id)}
                            >
                              <X className="h-4 w-4" />
                            </Button>
                          )}
                        </div>

                        <div className="space-y-2">
                          <VariableInput
                            placeholder="Option title"
                            value={row.title}
                            onChange={(value) => updateRow(section.id, row.id, 'title', value)}
                          />
                          <VariableInput
                            placeholder="Option description"
                            value={row.description}
                            onChange={(value) => updateRow(section.id, row.id, 'description', value)}
                          />
                        </div>

                        <Handle
                          type="source"
                          position={Position.Right}
                          id={`${section.id}-${row.id}`}
                          className="!bg-green-500 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white"
                          style={{
                            right: '-20px',
                            top: '50%',
                            transform: 'translateY(-50%)',
                            zIndex: 50
                          }}
                          isConnectable={isConnectable}
                        />
                      </div>
                    ))}
                  </div>
                </div>
              ))}

              <div className="mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2">
                <div className="flex flex-col items-end">
                  <span className="text-xs text-gray-500">Else exit</span>
                  <span className="text-[10px] text-gray-400">Triggered when user reply is not known option</span>
                </div>
                <Handle
                  type="source"
                  position={Position.Right}
                  id="else"
                  className="!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white"
                  style={{ 
                    position: 'relative',
                    right: '-8px',
                    transform: 'translateY(0)',
                    display: 'inline-block'
                  }}
                  isConnectable={isConnectable}
                />
              </div>
            </div>
          </div>
        </div>
      </ContextMenuTrigger>
      <ContextMenuContent>
        <ContextMenuItem
          className="text-red-600 focus:text-red-600 focus:bg-red-100"
          onClick={() => deleteNode(id)}
        >
          <Trash2 className="mr-2 h-4 w-4" />
          Delete
        </ContextMenuItem>
      </ContextMenuContent>
    </ContextMenu>
  );
};

export default ListMessageNode; 