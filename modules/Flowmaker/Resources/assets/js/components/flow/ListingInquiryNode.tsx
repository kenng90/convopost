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

type CompletionType = 'booking' | 'inquiry';
type BookingBackend = 'whatsapp_only' | 'reminders';
type DisplayMode = 'link' | 'interactive_list';

const DEFAULT_LISTING_HEADER = 'Browse our listings';
const DEFAULT_SERVICE_HEADER = 'Book our services';
const DEFAULT_LISTING_FOOTER = 'Tap the link to view listings and book on WhatsApp.';
const DEFAULT_SERVICE_FOOTER = 'Tap the link to view services and book on WhatsApp.';

const ListingInquiryNode = ({ data, id }: ListingInquiryNodeProps) => {
  const { deleteNode } = useFlowActions();

  const [selectedTemplateId, setSelectedTemplateId] = useState<string>(data.settings?.catalogId || "");
  const [catalogs, setCatalogs] = useState<Catalog[]>([]);
  const [header, setHeader] = useState<string>(data.settings?.header || DEFAULT_LISTING_HEADER);
  const [footer, setFooter] = useState<string>(
    data.settings?.footer || DEFAULT_LISTING_FOOTER
  );
  const [displayMode, setDisplayMode] = useState<DisplayMode>(
    (data.settings?.displayMode as DisplayMode) || 'link'
  );
  const [completionType, setCompletionType] = useState<CompletionType>(
    (data.settings?.completionType as CompletionType) || 'booking'
  );
  const [bookingVariablePrefix, setBookingVariablePrefix] = useState<string>(
    data.settings?.bookingVariablePrefix || 'listing_booking'
  );
  const [requirePreferredDateTime, setRequirePreferredDateTime] = useState<boolean>(
    !!data.settings?.requirePreferredDateTime
  );
  const [bookingBackend, setBookingBackend] = useState<BookingBackend>(
    (data.settings?.bookingBackend as BookingBackend) || 'whatsapp_only'
  );
  const [autoResumeFlow, setAutoResumeFlow] = useState<boolean>(!!data.settings?.autoResumeFlow);

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
      data.settings.displayMode = displayMode;
      data.settings.completionType = completionType;
      data.settings.bookingVariablePrefix = bookingVariablePrefix;
      data.settings.requirePreferredDateTime = requirePreferredDateTime;
      data.settings.bookingBackend = bookingBackend;
      data.settings.autoResumeFlow = autoResumeFlow;
    }
  }, [
    selectedTemplateId,
    header,
    footer,
    displayMode,
    completionType,
    bookingVariablePrefix,
    requirePreferredDateTime,
    bookingBackend,
    autoResumeFlow,
    data,
  ]);

  const handlePrefixChange = (value: string) => {
    const sanitized = value.replace(/[^a-zA-Z0-9_]/g, '') || 'listing_booking';
    setBookingVariablePrefix(sanitized);
  };

  const selectedCatalog = catalogs.find(c => c.id.toString() === selectedTemplateId);
  const isServiceCatalog = selectedCatalog?.catalog_mode === 'service';
  const headerPlaceholder = isServiceCatalog ? DEFAULT_SERVICE_HEADER : DEFAULT_LISTING_HEADER;
  const footerPlaceholder = isServiceCatalog ? DEFAULT_SERVICE_FOOTER : DEFAULT_LISTING_FOOTER;
  const canUseInteractiveList = selectedCatalog
    ? selectedCatalog.item_count > 0 && selectedCatalog.item_count <= 10
    : false;

  const handleCatalogSelect = (value: string) => {
    setSelectedTemplateId(value);
    const catalog = catalogs.find(c => c.id.toString() === value);
    if (catalog?.catalog_mode === 'service') {
      if (header === DEFAULT_LISTING_HEADER) {
        setHeader(DEFAULT_SERVICE_HEADER);
      }
      if (footer === DEFAULT_LISTING_FOOTER) {
        setFooter(DEFAULT_SERVICE_FOOTER);
      }
    }
  };

  const variablePrefix = (bookingVariablePrefix || 'listing_booking').replace(/[^a-zA-Z0-9_]/g, '') || 'listing_booking';
  const bookingVariables = [
    `${variablePrefix}_item_title`,
    `${variablePrefix}_customer_name`,
    `${variablePrefix}_customer_phone`,
    `${variablePrefix}_preferred_datetime`,
    `${variablePrefix}_notes`,
    `${variablePrefix}_message`,
    `${variablePrefix}_reservation_id`,
  ];

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
                Sends a branded listings page (or in-chat list for ≤10 items). Customers complete booking details on the web, then continue in WhatsApp.
              </p>

              <div className="space-y-2">
                <Label htmlFor="listing-header">Message header</Label>
                <textarea
                  id="listing-header"
                  placeholder={headerPlaceholder}
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
                  placeholder={footerPlaceholder}
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
                    onChange={(e) => handleCatalogSelect(e.target.value)}
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
                <div className="space-y-2">
                  <Label htmlFor="listing-display-mode">Display mode</Label>
                  <select
                    id="listing-display-mode"
                    value={displayMode}
                    onChange={(e) => setDisplayMode(e.target.value as DisplayMode)}
                    className="w-full px-2 py-2 text-xs border rounded bg-white"
                  >
                    <option value="link">Listings link (web page)</option>
                    <option value="interactive_list" disabled={!canUseInteractiveList}>
                      In-chat list (≤10 items){!canUseInteractiveList ? ' — unavailable' : ''}
                    </option>
                  </select>
                </div>
              )}

              <div className="space-y-2">
                <Label htmlFor="completion-type">Completion type</Label>
                <select
                  id="completion-type"
                  value={completionType}
                  onChange={(e) => setCompletionType(e.target.value as CompletionType)}
                  className="w-full px-2 py-2 text-xs border rounded bg-white"
                >
                  <option value="booking">Booking (form + variables)</option>
                  <option value="inquiry">Inquiry (lightweight)</option>
                </select>
              </div>

              {completionType === 'booking' && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="booking-variable-prefix">Booking variable prefix</Label>
                    <input
                      id="booking-variable-prefix"
                      type="text"
                      value={bookingVariablePrefix}
                      onChange={(e) => handlePrefixChange(e.target.value)}
                      placeholder="listing_booking"
                      className="w-full px-2 py-1 text-xs border rounded font-mono"
                    />
                    <p className="text-[10px] text-gray-500 leading-relaxed">
                      After the customer sends the booking message in WhatsApp, use:{' '}
                      {bookingVariables.map((name, index) => (
                        <span key={name}>
                          {index > 0 ? ', ' : ''}
                          <code className="text-[10px] bg-gray-100 px-1 rounded">{`{{${name}}}`}</code>
                        </span>
                      ))}
                    </p>
                  </div>

                  <label className="flex items-center gap-2 text-xs text-gray-700">
                    <input
                      type="checkbox"
                      checked={requirePreferredDateTime}
                      onChange={(e) => setRequirePreferredDateTime(e.target.checked)}
                    />
                    Require preferred date/time on the web form
                  </label>

                  <div className="space-y-2">
                    <Label htmlFor="booking-backend">Booking backend</Label>
                    <select
                      id="booking-backend"
                      value={bookingBackend}
                      onChange={(e) => setBookingBackend(e.target.value as BookingBackend)}
                      className="w-full px-2 py-2 text-xs border rounded bg-white"
                    >
                      <option value="whatsapp_only">WhatsApp only (message + variables)</option>
                      <option value="reminders">Reminders (create reservation when possible)</option>
                    </select>
                    <p className="text-[10px] text-gray-500 leading-relaxed">
                      For Reminders, link each listing item to a bookable service via metadata{' '}
                      <code className="bg-gray-100 px-1 rounded">booking_source_id</code>.
                    </p>
                  </div>
                  <label className="flex items-center gap-2 text-xs text-gray-700 mt-2">
                    <input
                      type="checkbox"
                      checked={autoResumeFlow}
                      onChange={(e) => setAutoResumeFlow(e.target.checked)}
                    />
                    Auto-continue flow after web form (no manual WhatsApp message)
                  </label>
                </>
              )}

              {selectedCatalog && (
                <div className="bg-emerald-50 border border-emerald-200 p-2 rounded text-xs">
                  <div className="font-medium text-emerald-900">{selectedCatalog.name}</div>
                  <div className="text-gray-600">{selectedCatalog.item_count} listings</div>
                </div>
              )}
            </div>
          </div>

          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-gray-500">After booking</span>
            <Handle type="source" position={Position.Right} id="onBooking" className="!bg-green-600 !w-3 !h-3 !border-2 !border-white" />
          </div>

          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-white">
            <span className="text-xs text-gray-500">After inquiry</span>
            <Handle type="source" position={Position.Right} id="onInquiry" className="!bg-sky-500 !w-3 !h-3 !border-2 !border-white" />
          </div>

          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-xs text-gray-500">After booking / inquiry (any)</span>
            <Handle
              type="source"
              position={Position.Right}
              id="onListingInquiry"
              className="!bg-green-500 !w-3 !h-3 !border-2 !border-white"
            />
          </div>

          <div className="flex items-center justify-center px-4 py-2 border-t border-gray-100 bg-white">
            <span className="text-xs text-gray-500 mr-2">No booking</span>
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
