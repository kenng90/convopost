import React, { useState, useEffect } from 'react';
import { Handle, Position } from '@xyflow/react';
import { Database, Trash2 } from 'lucide-react';
import { Label } from "@/components/ui/label";
import { NodeData } from '@/types/flow';
import { 
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger 
} from "@/components/ui/context-menu";
import { useFlowActions } from "@/hooks/useFlowActions";

interface WhatsAppCatalogNodeProps {
  data: NodeData;
  id: string;
}

interface NodeSettings {
  catalogId?: number;
  header?: string;
}

interface Catalog {
  id: number;
  name: string;
  item_count: number;
  version: number;
}

const WhatsAppCatalogNode = ({ data, id }: WhatsAppCatalogNodeProps) => {
  console.log('WhatsAppCatalogNode: COMPONENT RENDERED');
  const { deleteNode } = useFlowActions();
  
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>(data.settings?.catalogId || "");
  const [catalogs, setCatalogs] = useState<Catalog[]>([]);
  const [header, setHeader] = useState<string>(data.settings?.header || 'Browse our products');

  // Load catalogs on mount
  useEffect(() => {
    console.log('WhatsAppCatalogNode: useEffect triggered');
    const loadCatalogs = async () => {
      console.log('WhatsAppCatalogNode: loadCatalogs called');
      try {
        const response = await fetch('/api/list-catalogs');
        const result = await response.json();
        console.log('WhatsAppCatalogNode: fetch result', result);
        
        if (result.success && Array.isArray(result.catalogs)) {
          setCatalogs(result.catalogs);
        }
      } catch (error) {
        console.error('WhatsAppCatalogNode: Error loading catalogs:', error);
      }
    };

    loadCatalogs();
  }, []);

  // Persist settings to node data
  useEffect(() => {
    if (data && data.settings) {
      data.settings.catalogId = selectedTemplateId;
      data.settings.header = header;
    }
  }, [selectedTemplateId, header, data]);

  const handleCatalogSelect = (value: string) => {
    console.log('WhatsAppCatalogNode: catalog selected', value);
    setSelectedTemplateId(value);
    if (data && data.settings) {
      data.settings.catalogId = value;
    }
  };

  const handleHeaderChange = (value: string) => {
    setHeader(value);
    if (data && data.settings) {
      data.settings.header = value;
    }
  };

  const selectedCatalog = catalogs.find(c => c.id.toString() === selectedTemplateId);

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="bg-white rounded-lg shadow-lg w-[300px]">
          <Handle
            type="target"
            position={Position.Left}
            style={{ left: '-4px', background: '#555', zIndex: 50 }}
          />
          
          <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
            <Database className="h-4 w-4 text-purple-600" />
            <div className="font-medium">WhatsApp Catalog</div>
          </div>

          <div className="p-4">
            <div className="space-y-4">
              {/* Header Text */}
              <div className="space-y-2">
                <Label htmlFor="header">Message Header</Label>
                <textarea
                  id="header"
                  placeholder="Browse our products"
                  value={header}
                  onChange={(e) => handleHeaderChange(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
                <p className="text-xs text-gray-500">Text to show above the catalog</p>
              </div>

              {/* Catalog Selection */}
              <div className="space-y-2">
                <Label htmlFor="catalog-select">Select Catalog</Label>
                {catalogs.length === 0 ? (
                  <div className="text-xs text-gray-500 p-2 bg-gray-50 rounded border border-gray-200">
                    No catalogs found. Import one via List Message node.
                  </div>
                ) : (
                  <select
                    id="catalog-select"
                    value={selectedTemplateId}
                    onChange={(e) => handleCatalogSelect(e.target.value)}
                    className="w-full px-2 py-2 text-xs border rounded bg-white"
                  >
                    <option value="">-- Choose a catalog --</option>
                    {catalogs.map(cat => (
                      <option key={cat.id} value={cat.id}>
                        {cat.name} ({cat.item_count} items)
                      </option>
                    ))}
                  </select>
                )}
              </div>

              {/* Selected Catalog Preview */}
              {selectedCatalog && (
                <div className="bg-purple-50 border border-purple-200 p-2 rounded text-xs">
                  <div className="font-medium text-purple-900">{selectedCatalog.name}</div>
                  <div className="text-gray-600">{selectedCatalog.item_count} items</div>
                </div>
              )}
            </div>
          </div>

          {/* Output Handles */}
          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-gray-500">On selection</span>
            <Handle
              type="source"
              position={Position.Right}
              id="onProductSelected"
              className="!bg-green-500 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

          {/* Else Handle */}
          <div className="flex items-center justify-center px-4 py-2 border-t border-gray-100 bg-white">
            <span className="text-xs text-gray-500 mr-2">No selection</span>
            <Handle
              type="source"
              position={Position.Bottom}
              id="else"
              className="!bg-gray-400 !w-3 !h-3 !border-2 !border-white"
            />
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

export default WhatsAppCatalogNode;
