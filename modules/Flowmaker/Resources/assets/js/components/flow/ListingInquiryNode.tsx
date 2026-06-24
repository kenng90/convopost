import React, { useState, useEffect } from 'react';
import { Handle, Position } from '@xyflow/react';
import { Building2, Trash2 } from 'lucide-react';
import { Label } from "@/components/ui/label";
import { NodeData } from '@/types/flow';
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger
} from "@/components/ui/context-menu";
import { useFlowActions } from "@/hooks/useFlowActions";

interface ListingInquiryNodeProps {
  data: NodeData;
  id: string;
}

interface Catalog {
  id: number;
  name: string;
  item_count: number;
  catalog_mode?: string;
  presentation?: {
    vertical_label?: string;
  };
}

const ListingInquiryNode = ({ data, id }: ListingInquiryNodeProps) => {
  const { deleteNode } = useFlowActions();

  const [selectedTemplateId, setSelectedTemplateId] = useState<string>(data.settings?.catalogId || "");
  const [catalogs, setCatalogs] = useState<Catalog[]>([]);
  const [header, setHeader] = useState<string>(data.settings?.header || 'Browse our listings');
  const [footer, setFooter] = useState<string>(
    data.settings?.footer || 'Tap the link to view listings and inquire on WhatsApp.'
  );

  useEffect(() => {
    const loadCatalogs = async () => {
      try {
        const response = await fetch('/api/list-catalogs');
        const result = await response.json();

        if (result.success && Array.isArray(result.catalogs)) {
          setCatalogs(
            result.catalogs.filter((catalog: Catalog) => catalog.catalog_mode !== 'commerce')
          );
        }
      } catch (error) {
        console.error('Error loading listing catalogs:', error);
      }
    };

    loadCatalogs();
  }, []);

  useEffect(() => {
    if (data && data.settings) {
      data.settings.catalogId = selectedTemplateId;
      data.settings.header = header;
      data.settings.footer = footer;
    }
  }, [selectedTemplateId, header, footer, data]);

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
            <Building2 className="h-4 w-4 text-emerald-600" />
            <div className="font-medium">Send Listings Link</div>
          </div>

          <div className="p-4">
            <div className="space-y-4">
              <p className="text-xs text-gray-500 leading-relaxed">
                Sends a branded listings page. When the customer inquires on WhatsApp, the flow can continue.
              </p>

              <div className="space-y-2">
                <Label htmlFor="listing-header">Message header</Label>
                <textarea
                  id="listing-header"
                  value={header}
                  onChange={(e) => setHeader(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="listing-footer">Message footer</Label>
                <textarea
                  id="listing-footer"
                  value={footer}
                  onChange={(e) => setFooter(e.target.value)}
                  rows={2}
                  className="w-full px-2 py-1 text-xs border rounded"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="listing-catalog-select">Listing catalog</Label>
                {catalogs.length === 0 ? (
                  <div className="text-xs text-gray-500 p-2 bg-gray-50 rounded border border-gray-200">
                    No listing catalogs yet. Create one under Catalogs & Listings.
                  </div>
                ) : (
                  <select
                    id="listing-catalog-select"
                    value={selectedTemplateId}
                    onChange={(e) => setSelectedTemplateId(e.target.value)}
                    className="w-full px-2 py-2 text-xs border rounded bg-white"
                  >
                    <option value="">-- Choose a listing catalog --</option>
                    {catalogs.map(cat => (
                      <option key={cat.id} value={cat.id}>
                        {cat.name} ({cat.presentation?.vertical_label || cat.catalog_mode}, {cat.item_count} items)
                      </option>
                    ))}
                  </select>
                )}
              </div>

              {selectedCatalog && (
                <div className="bg-emerald-50 border border-emerald-200 p-2 rounded text-xs">
                  <div className="font-medium text-emerald-900">{selectedCatalog.name}</div>
                  <div className="text-gray-600">{selectedCatalog.item_count} listings</div>
                </div>
              )}
            </div>
          </div>

          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-gray-500">After inquiry</span>
            <Handle
              type="source"
              position={Position.Right}
              id="onListingInquiry"
              className="!bg-green-500 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

          <div className="flex items-center justify-center px-4 py-2 border-t border-gray-100 bg-white">
            <span className="text-xs text-gray-500 mr-2">No inquiry</span>
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

export default ListingInquiryNode;
