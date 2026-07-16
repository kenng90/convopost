import { Database, Search, CreditCard, PackageCheck, ClipboardList } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useFlowActions } from '@/hooks/useFlowActions';
import { NodeData } from '@/types/flow';
import { useEffect, useState } from 'react';

interface SellSectionProps {
  searchQuery: string;
}

declare global {
  interface Window {
    data?: {
      planPlugins?: {
        whatsappcatalog?: boolean;
        whatsappflows?: boolean;
      };
    };
  }
}

interface FormsHealth {
  ready?: boolean;
  totals?: {
    forms?: number;
    live?: number;
    completed_30d?: number;
    abandoned_30d?: number;
  };
  links?: {
    forms?: string;
    responses?: string;
    keys?: string;
  };
}

export const SellSection = ({ searchQuery }: SellSectionProps) => {
  const actions = useFlowActions();
  const planPlugins = window.data?.planPlugins ?? {};
  const catalogEnabled = Boolean(planPlugins.whatsappcatalog);
  const formsEnabled = Boolean(planPlugins.whatsappflows);
  const [formsHealth, setFormsHealth] = useState<FormsHealth | null>(null);

  useEffect(() => {
    if (!formsEnabled) return;
    let cancelled = false;
    (async () => {
      try {
        const response = await fetch('/api/whatsapp-flows/health', {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'include',
        });
        if (!response.ok) return;
        const data = await response.json();
        if (!cancelled && data.success) {
          setFormsHealth(data);
        }
      } catch {
        // ignore
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [formsEnabled]);

  const options = [
    {
      type: 'whatsapp_catalog',
      icon: Database,
      label: 'Send catalog',
      bgColor: 'bg-purple-100',
      textColor: 'text-purple-600',
      requiresCatalog: true,
      requiresForms: false,
      onClick: () => {
        const data: NodeData = {
          label: 'Send Catalog',
          type: 'whatsapp_catalog',
          settings: {
            catalogId: undefined,
            header: 'Browse our products',
            displayMode: 'interactive_list',
            checkoutVariablePrefix: 'catalog_order',
            autoResumeFlow: true,
          },
        };
        return actions.createNodeBase('whatsapp_catalog', { x: 250, y: 100 }, data);
      },
    },
    {
      type: 'catalog_search',
      icon: Search,
      label: 'Catalog search',
      bgColor: 'bg-violet-100',
      textColor: 'text-violet-600',
      requiresCatalog: true,
      requiresForms: false,
      onClick: () => actions.createNodeCatalogSearch({ x: 250, y: 100 }),
    },
    {
      type: 'listing_inquiry',
      icon: Database,
      label: 'Send listings',
      bgColor: 'bg-emerald-100',
      textColor: 'text-emerald-600',
      requiresCatalog: true,
      requiresForms: false,
      onClick: () => {
        const data: NodeData = {
          label: 'Send Listings',
          type: 'listing_inquiry',
          settings: {
            catalogId: undefined,
            header: 'Browse our listings',
            footer: 'Tap to view listings and inquire on WhatsApp.',
            displayMode: 'interactive_list',
            completionType: 'inquiry',
            bookingVariablePrefix: 'listing_booking',
            requirePreferredDateTime: false,
            bookingBackend: 'whatsapp_only',
            autoResumeFlow: true,
          },
        };
        return actions.createNodeBase('listing_inquiry', { x: 250, y: 100 }, data);
      },
    },
    {
      type: 'whatsapp_flow',
      icon: ClipboardList,
      label: 'Collect with WhatsApp Form',
      bgColor: 'bg-sky-100',
      textColor: 'text-sky-700',
      requiresCatalog: false,
      requiresForms: true,
      onClick: () => {
        const data: NodeData = {
          label: 'Collect with WhatsApp Form',
          type: 'whatsapp_flow',
          settings: {
            whatsappFlowId: undefined,
            header: 'Complete the form',
            footer: 'Your responses help us serve you better',
            fieldMappings: [],
            onComplete: { groupId: 'none', journeyId: 'none', stageId: 'none' },
          },
        };
        return actions.createNodeBase('whatsapp_flow', { x: 250, y: 100 }, data);
      },
    },
    {
      type: 'request_payment',
      icon: CreditCard,
      label: 'Collect payment',
      bgColor: 'bg-blue-100',
      textColor: 'text-blue-700',
      requiresCatalog: false,
      requiresForms: false,
      onClick: () => actions.createNodeRequestPayment({ x: 250, y: 100 }),
    },
    {
      type: 'order_status',
      icon: PackageCheck,
      label: 'Update order status',
      bgColor: 'bg-amber-100',
      textColor: 'text-amber-700',
      requiresCatalog: false,
      requiresForms: false,
      onClick: () => actions.createNodeOrderStatus({ x: 250, y: 100 }),
    },
  ];

  const filtered = options.filter((option) => {
    if (option.requiresForms && !formsEnabled) {
      return false;
    }
    return option.label.toLowerCase().includes(searchQuery.toLowerCase());
  });

  if (filtered.length === 0) {
    return null;
  }

  return (
    <div className="grid gap-2">
      {!catalogEnabled && (
        <div className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md p-2">
          Catalog nodes need the WhatsApp Catalog plugin on your plan. Forms and payment still work when enabled.
        </div>
      )}
      {formsEnabled && (
        <div className="text-xs text-slate-700 bg-slate-50 border border-slate-200 rounded-md p-2 space-y-1">
          <div className="font-medium">WhatsApp Forms health</div>
          <div>
            Live forms: {formsHealth?.totals?.live ?? '—'} / {formsHealth?.totals?.forms ?? '—'}
            {' · '}
            Completed (30d): {formsHealth?.totals?.completed_30d ?? '—'}
            {' · '}
            Abandoned: {formsHealth?.totals?.abandoned_30d ?? '—'}
          </div>
          <div className="flex flex-wrap gap-2">
            {formsHealth?.links?.forms && (
              <a href={formsHealth.links.forms} className="text-sky-700 underline">All forms</a>
            )}
            {formsHealth?.links?.responses && (
              <a href={formsHealth.links.responses} className="text-sky-700 underline">Submissions</a>
            )}
            {formsHealth?.links?.keys && (
              <a href={formsHealth.links.keys} className="text-sky-700 underline">Setup keys</a>
            )}
          </div>
          <div className="text-slate-600">
            Forms must be Live on WhatsApp before they can be sent from automation.
          </div>
        </div>
      )}
      {filtered.map((option, index) => {
        const disabled = option.requiresCatalog && !catalogEnabled;
        return (
          <Button
            key={index}
            variant="ghost"
            disabled={disabled}
            className="w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none disabled:opacity-50"
            onClick={option.onClick}
          >
            <div className={`${option.bgColor} p-2 rounded-lg mr-3`}>
              <option.icon className={`h-5 w-5 ${option.textColor}`} />
            </div>
            {option.label}
          </Button>
        );
      })}
    </div>
  );
};
