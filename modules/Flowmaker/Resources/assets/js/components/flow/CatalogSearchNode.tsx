import React, { useState, useEffect } from 'react';
import { Handle, Position } from '@xyflow/react';
import { Search, Trash2 } from 'lucide-react';
import { Label } from "@/components/ui/label";
import { NodeData } from '@/types/flow';
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger
} from "@/components/ui/context-menu";
import { useFlowActions } from "@/hooks/useFlowActions";

interface CatalogSearchNodeProps {
  data: NodeData;
  id: string;
}

interface Catalog {
  id: number;
  name: string;
  item_count: number;
  version: number;
  catalog_mode?: string;
}

const CatalogSearchNode = ({ data, id }: CatalogSearchNodeProps) => {
  const { deleteNode } = useFlowActions();

  const [catalogId, setCatalogId] = useState<string>(
    data.settings?.catalogId != null ? String(data.settings.catalogId) : ''
  );
  const [catalogs, setCatalogs] = useState<Catalog[]>([]);
  const [searchPrompt, setSearchPrompt] = useState<string>(
    data.settings?.searchPrompt || 'What are you looking for?'
  );
  const [maxResults, setMaxResults] = useState<number>(
    data.settings?.maxResults ?? 5
  );
  const [header, setHeader] = useState<string>(
    data.settings?.header || 'Search results'
  );

  useEffect(() => {
    const loadCatalogs = async () => {
      try {
        const response = await fetch('/api/list-catalogs');
        const result = await response.json();

        if (result.success && Array.isArray(result.catalogs)) {
          setCatalogs(
            result.catalogs.filter((catalog: Catalog) => catalog.catalog_mode === 'commerce')
          );
        }
      } catch (error) {
        console.error('Error loading catalogs:', error);
      }
    };

    loadCatalogs();
  }, []);

  useEffect(() => {
    if (data && data.settings) {
      data.settings.catalogId = catalogId;
      data.settings.searchPrompt = searchPrompt;
      data.settings.maxResults = maxResults;
      data.settings.header = header;
    }
  }, [catalogId, searchPrompt, maxResults, header, data]);

  const handleCatalogSelect = (value: string) => {
    setCatalogId(value);
    if (data && data.settings) {
      data.settings.catalogId = value;
    }
  };

  const clampMaxResults = (value: number) => Math.min(10, Math.max(1, value || 1));

  const selectedCatalog = catalogs.find(c => c.id.toString() === catalogId);

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
            <Search className="h-4 w-4 text-purple-600" />
            <div className="font-medium">Catalog Search</div>
          </div>

          <div className="p-4">
            <div className="space-y-4">
              <p className="text-xs text-gray-500 leading-relaxed">
                Asks the contact for a keyword, searches the catalog, and continues on match or no match.
              </p>

              <div className="space-y-2">
                <Label htmlFor="catalog-search-select">Catalog</Label>
                {catalogs.length === 0 ? (
                  <div className="text-xs text-gray-500 p-2 bg-gray-50 rounded border border-gray-200">
                    No catalogs yet. Create one under Automations → Product catalogs.
                  </div>
                ) : (
                  <select
                    id="catalog-search-select"
                    value={catalogId}
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

              <div className="space-y-2">
                <Label htmlFor="search-prompt">Search prompt</Label>
                <textarea
                  id="search-prompt"
                  placeholder="What are you looking for?"
                  value={searchPrompt}
                  onChange={(e) => setSearchPrompt(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="max-results">Max results (1–10)</Label>
                <input
                  id="max-results"
                  type="number"
                  min={1}
                  max={10}
                  value={maxResults}
                  onChange={(e) => setMaxResults(clampMaxResults(parseInt(e.target.value, 10)))}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="search-header">Results header</Label>
                <input
                  id="search-header"
                  type="text"
                  placeholder="Search results"
                  value={header}
                  onChange={(e) => setHeader(e.target.value)}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
              </div>

              {selectedCatalog && (
                <div className="bg-purple-50 border border-purple-200 p-2 rounded text-xs">
                  <div className="font-medium text-purple-900">{selectedCatalog.name}</div>
                  <div className="text-gray-600">{selectedCatalog.item_count} items</div>
                </div>
              )}
            </div>
          </div>

          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-green-700">On match</span>
            <Handle
              type="source"
              position={Position.Right}
              id="onMatch"
              className="!bg-green-500 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-white">
            <span className="text-xs text-amber-700">No match</span>
            <Handle
              type="source"
              position={Position.Right}
              id="onNoMatch"
              className="!bg-amber-400 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

          <div className="flex items-center justify-center px-4 py-2 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-gray-500 mr-2">Else</span>
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

export default CatalogSearchNode;
