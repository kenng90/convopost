import {
  ReactFlow,
  Background,
  useNodesState,
  useEdgesState,
  addEdge,
  Connection,
  Edge,
  Node,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Save, ArrowLeft, Upload, Play } from "lucide-react";
import { useToast } from "@/components/ui/use-toast";
import { useCallback, useMemo, useState } from 'react';
import ActionPanel from './ActionPanel';
import { nodeTypes } from '@/config/nodeTypes';
import DataSidebar from './DataSidebar';

interface FlowCanvasProps {
  flowId?: string;
}

function parseFlowJson(raw: unknown): { nodes: Node[]; edges: Edge[] } {
  if (!raw) {
    return { nodes: [], edges: [] };
  }

  const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;

  return {
    nodes: parsed?.nodes || [],
    edges: parsed?.edges || [],
  };
}

const editorSource = window.data?.flow?.draft_flow_data || window.data?.flow?.flow_data || '{}';
const parsedEditor = parseFlowJson(editorSource);

function normalizeFlowNodes(nodes: Node[]): Node[] {
  return nodes.map(node => {
    const dataType = (node.data as { type?: string })?.type;

    if (node.type === 'action' && dataType === 'book_appointment') {
      return { ...node, type: 'book_appointment' };
    }

    if (node.type === 'action' && dataType === 'booking_events_list') {
      return { ...node, type: 'booking_events_list' };
    }

    if (node.type === 'action' && dataType === 'booking_event_register') {
      return { ...node, type: 'booking_event_register' };
    }

    if (node.type === 'action' && dataType === 'send_booking_link') {
      return { ...node, type: 'send_booking_link' };
    }

    if (node.type === 'action' && dataType === 'manage_booking') {
      return { ...node, type: 'manage_booking' };
    }

    return node;
  });
}

const initialNodes: Node[] = normalizeFlowNodes(parsedEditor.nodes);
const initialEdges: Edge[] = parsedEditor.edges;

const defaultEdgeOptions = {
  animated: true,
  style: {
    stroke: '#000000',
  },
};

const TRIGGER_TYPES = new Set(['keyword_trigger', 'incomingMessage', 'template', 'opening_hours']);

const FlowCanvas = ({ flowId = '1' }: FlowCanvasProps) => {
  const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);
  const [dataDrawerOpen, setDataDrawerOpen] = useState(false);
  const [hasUnpublishedChanges, setHasUnpublishedChanges] = useState(
    Boolean(window.data?.flow?.has_unpublished_changes)
  );
  const [healthMessages, setHealthMessages] = useState<string[]>([]);
  const [simulateMessage, setSimulateMessage] = useState('');
  const [simulateResult, setSimulateResult] = useState<string | null>(null);
  const installChecklist: string[] = window.data?.post_install_checklist || [];
  const { toast } = useToast();

  const buildFlowPayload = useCallback(() => {
    const nodesWithData = nodes.map(node => ({
      ...node,
      data: {
        ...node.data,
        settings: node.data?.settings || {},
      },
    }));

    return { nodes: nodesWithData, edges };
  }, [nodes, edges]);

  const isValidConnection = useCallback(
    (connection: Connection) => {
      const sourceNode = nodes.find((node) => node.id === connection.source);
      const targetNode = nodes.find((node) => node.id === connection.target);

      if (!sourceNode || !targetNode) {
        return false;
      }

      if (sourceNode.type === 'end') {
        return false;
      }

      if (TRIGGER_TYPES.has(targetNode.type as string)) {
        return false;
      }

      return true;
    },
    [nodes],
  );

  const onConnect = useCallback(
    (params: Connection | Edge) => {
      if (!isValidConnection(params as Connection)) {
        toast({
          title: 'Invalid connection',
          description: 'End nodes cannot connect outward, and triggers cannot be connection targets.',
          variant: 'destructive',
        });
        return;
      }

      const newEdge = {
        ...params,
        id: `e${Date.now()}`,
      };
      return setEdges((eds) => addEdge(newEdge, eds));
    },
    [setEdges, isValidConnection, toast],
  );

  const showHealth = (health: { errors?: string[]; warnings?: string[] }) => {
    const messages = [
      ...(health.errors || []).map((item) => `Error: ${item}`),
      ...(health.warnings || []).map((item) => `Warning: ${item}`),
    ];
    setHealthMessages(messages);
  };

  const handleSave = async () => {
    const flowData = buildFlowPayload();

    try {
      const response = await fetch(`/flowmaker/update/${window.data.flow.id}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(flowData),
      });

      if (!response.ok) {
        throw new Error('Failed to save flow');
      }

      const result = await response.json();
      setHasUnpublishedChanges(Boolean(result.has_unpublished_changes));
      if (result.health) {
        showHealth(result.health);
      }

      toast({
        title: 'Draft saved',
        description: result.health?.errors?.length
          ? 'Draft saved with errors — fix before publishing.'
          : 'Draft saved. Publish when ready to go live.',
      });
    } catch (error) {
      console.error('Error saving flow:', error);
      toast({
        title: 'Save failed',
        description: 'Failed to save flow. Please try again.',
        variant: 'destructive',
      });
    }
  };

  const handlePublish = async () => {
    try {
      const response = await fetch(`/flowmaker/publish/${window.data.flow.id}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(buildFlowPayload()),
      });

      const result = await response.json();

      if (!response.ok) {
        if (result.health) {
          showHealth(result.health);
        }
        throw new Error(result.message || 'Failed to publish flow');
      }

      setHasUnpublishedChanges(false);
      if (result.health) {
        showHealth(result.health);
      }

      toast({
        title: 'Flow published',
        description: 'Your flow is now live for customers.',
      });
    } catch (error) {
      console.error('Error publishing flow:', error);
      toast({
        title: 'Publish failed',
        description: error instanceof Error ? error.message : 'Failed to publish flow.',
        variant: 'destructive',
      });
    }
  };

  const handleSimulate = async () => {
    try {
      const response = await fetch(`/flowmaker/simulate/${window.data.flow.id}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ message: simulateMessage }),
      });

      const result = await response.json();
      if (result.health) {
        showHealth(result.health);
      }

      const matched = result.simulation?.matched_keywords?.join(', ') || 'none';
      setSimulateResult(
        `Matched keywords: ${matched}. Would start: ${result.simulation?.would_start ? 'yes' : 'no'}`,
      );
    } catch (error) {
      toast({
        title: 'Simulation failed',
        description: 'Could not run flow simulation.',
        variant: 'destructive',
      });
    }
  };

  const healthSummary = useMemo(() => healthMessages.slice(0, 5), [healthMessages]);

  const handlePopoverIndexChange = () => {};
  const handleOpenDataSidebar = () => setDataDrawerOpen(true);
  const handleBackClick = () => {
    window.location.href = '/flows';
  };

  return (
    <div className="flex h-screen bg-[#F1F0FB] relative">
      <div className="absolute left-0 top-0 m-2.5 z-10">
        <div className="w-20 flex flex-col items-center py-4 space-y-6">
          <div className="flex flex-col items-center space-y-6">
            <ActionPanel
              onImportClick={() => {}}
              onPopoverIndexChange={handlePopoverIndexChange}
              onOpenDataSidebar={handleOpenDataSidebar}
            />
          </div>
        </div>
      </div>

      <div className="flex-1 relative">
        <div className="absolute top-4 right-4 z-10 flex flex-col items-end gap-2">
          <div className="flex space-x-2">
            <Button onClick={handleBackClick} variant="outline" size="default" className="gap-2 bg-white">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Button>
            <Button onClick={handleSave} variant="outline" size="default" className="gap-2 bg-white">
              <Save className="h-4 w-4" />
              Save draft
            </Button>
            <Button onClick={handlePublish} size="default" className="gap-2">
              <Upload className="h-4 w-4" />
              Publish
            </Button>
          </div>
          {hasUnpublishedChanges && (
            <span className="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded">
              Unpublished draft changes
            </span>
          )}
        </div>

        <div className="absolute top-4 left-24 z-10 w-80 bg-white rounded-lg shadow p-3 space-y-2">
          <div className="text-sm font-medium">Test message</div>
          <div className="flex gap-2">
            <Input
              value={simulateMessage}
              onChange={(e) => setSimulateMessage(e.target.value)}
              placeholder="Type a test message..."
            />
            <Button size="icon" variant="outline" onClick={handleSimulate}>
              <Play className="h-4 w-4" />
            </Button>
          </div>
          {installChecklist.length > 0 && (
            <div className="text-xs bg-blue-50 text-blue-900 px-2 py-2 rounded space-y-1 max-h-28 overflow-y-auto">
              <div className="font-medium">Setup checklist</div>
              {installChecklist.map((item) => (
                <div key={item}>• {item}</div>
              ))}
            </div>
          )}
          {simulateResult && <p className="text-xs text-gray-600">{simulateResult}</p>}
          {healthSummary.length > 0 && (
            <div className="text-xs text-gray-700 space-y-1 max-h-28 overflow-y-auto">
              {healthSummary.map((item) => (
                <div key={item}>{item}</div>
              ))}
            </div>
          )}
        </div>

        <ReactFlow
          nodes={nodes}
          edges={edges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          isValidConnection={isValidConnection}
          nodeTypes={nodeTypes}
          defaultEdgeOptions={defaultEdgeOptions}
          minZoom={0.1}
          maxZoom={4}
          fitView
        >
          <Background />
        </ReactFlow>

        <DataSidebar open={dataDrawerOpen} onOpenChange={setDataDrawerOpen} />
      </div>
    </div>
  );
};

export default FlowCanvas;
