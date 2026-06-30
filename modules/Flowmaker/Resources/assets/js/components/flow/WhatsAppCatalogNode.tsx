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
  displayMode?: 'link' | 'interactive_list';
  checkoutVariablePrefix?: string;
}

interface Catalog {
  id: number;
  name: string;
  item_count: number;
  version: number;
}

const WhatsAppCatalogNode = ({ data, id }: WhatsAppCatalogNodeProps) => {
  const { deleteNode } = useFlowActions();
  
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>(data.settings?.catalogId || "");
  const [catalogs, setCatalogs] = useState<Catalog[]>([]);
  const [header, setHeader] = useState<string>(data.settings?.header || 'Browse our products');
  const [displayMode, setDisplayMode] = useState<'link' | 'interactive_list'>(
    data.settings?.displayMode || 'link'
  );
  const [checkoutVariablePrefix, setCheckoutVariablePrefix] = useState<string>(
    data.settings?.checkoutVariablePrefix || 'catalog_order'
  );

  useEffect(() => {
    const loadCatalogs = async () => {
      try {
        const response = await fetch('/api/list-catalogs');
        const result = await response.json();
        
        if (result.success && Array.isArray(result.catalogs)) {
          setCatalogs(result.catalogs);
        }
      } catch (error) {
        console.error('Error loading catalogs:', error);
      }
    };

    loadCatalogs();
  }, []);

  useEffect(() => {
    if (data && data.settings) {
      data.settings.catalogId = selectedTemplateId;
      data.settings.header = header;
      data.settings.displayMode = displayMode;
      data.settings.checkoutVariablePrefix = checkoutVariablePrefix;
    }
  }, [selectedTemplateId, header, displayMode, checkoutVariablePrefix, data]);

  const handleCatalogSelect = (value: string) => {
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

  const handleCheckoutPrefixChange = (value: string) => {
    const sanitized = value.replace(/[^a-zA-Z0-9_]/g, '');
    setCheckoutVariablePrefix(sanitized || 'catalog_order');
    if (data && data.settings) {
      data.settings.checkoutVariablePrefix = sanitized || 'catalog_order';
    }
  };

  const selectedCatalog = catalogs.find(c => c.id.toString() === selectedTemplateId);
  const variablePrefix = (checkoutVariablePrefix || 'catalog_order').replace(/[^a-zA-Z0-9_]/g, '') || 'catalog_order';
  const checkoutVariables = [
    `${variablePrefix}_items`,
    `${variablePrefix}_total`,
    `${variablePrefix}_item_count`,
    `${variablePrefix}_message`,
    `${variablePrefix}_json`,
    'catalog_cart',
  ];
  const canUseInteractiveList = selectedCatalog ? selectedCatalog.item_count > 0 && selectedCatalog.item_count <= 10 : false;

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
            <div className="font-medium">Send Catalog Link</div>
          </div>

          <div className="p-4">
            <div className="space-y-4">
              <p className="text-xs text-gray-500 leading-relaxed">
                Sends a branded shop link (or in-chat product list for ≤10 items). When opened from a flow, checkout resumes automation.
              </p>

              <div className="space-y-2">
                <Label htmlFor="header">Message header</Label>
                <textarea
                  id="header"
                  placeholder="Browse our products"
                  value={header}
                  onChange={(e) => handleHeaderChange(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="catalog-select">Catalog</Label>
                {catalogs.length === 0 ? (
                  <div className="text-xs text-gray-500 p-2 bg-gray-50 rounded border border-gray-200">
                    No catalogs yet. Create one under Automations → Product catalogs.
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

              {selectedCatalog && (
                <div className="space-y-2">
                  <Label htmlFor="display-mode">Display mode</Label>
                  <select
                    id="display-mode"
                    value={displayMode}
                    onChange={(e) => setDisplayMode(e.target.value as 'link' | 'interactive_list')}
                    className="w-full px-2 py-2 text-xs border rounded bg-white"
                  >
                    <option value="link">Shop link (web storefront)</option>
                    <option value="interactive_list" disabled={!canUseInteractiveList}>
                      In-chat product list (≤10 items){!canUseInteractiveList ? ' — unavailable' : ''}
                    </option>
                  </select>
                </div>
              )}

              {selectedCatalog && (
                <div className="space-y-2">
                  <Label htmlFor="checkout-variable-prefix">Checkout variable prefix</Label>
                  <input
                    id="checkout-variable-prefix"
                    type="text"
                    value={checkoutVariablePrefix}
                    onChange={(e) => handleCheckoutPrefixChange(e.target.value)}
                    placeholder="catalog_order"
                    className="w-full px-2 py-1 text-xs border rounded font-mono"
                  />
                  <p className="text-[10px] text-gray-500 leading-relaxed">
                    After WhatsApp checkout (when the user sends the order message), use these in downstream nodes:{' '}
                    {checkoutVariables.map((name, index) => (
                      <span key={name}>
                        {index > 0 ? ', ' : ''}
                        <code className="text-[10px] bg-gray-100 px-1 rounded">{`{{${name}}}`}</code>
                      </span>
                    ))}
                    .
                  </p>
                </div>
              )}

              {selectedCatalog && (
                <div className="bg-purple-50 border border-purple-200 p-2 rounded text-xs">
                  <div className="font-medium text-purple-900">{selectedCatalog.name}</div>
                  <div className="text-gray-600">{selectedCatalog.item_count} items</div>
                </div>
              )}
            </div>
          </div>

          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-gray-500">After checkout / selection</span>
            <Handle
              type="source"
              position={Position.Right}
              id="onProductSelected"
              className="!bg-green-500 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

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
