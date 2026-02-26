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
import { Save, ArrowLeft } from "lucide-react";
import { useToast } from "@/components/ui/use-toast";
import { useCallback, useState } from 'react';
import ActionPanel from './ActionPanel';
import { nodeTypes } from '@/config/nodeTypes';
import DataSidebar from './DataSidebar';

interface FlowCanvasProps {
  flowId?: string;
}

const flow_data = window.data?.flow?.flow_data || "{}";
const initialNodes: Node[] = JSON.parse(flow_data)?.nodes || [
];

const initialEdges: Edge[] = JSON.parse(flow_data)?.edges || [];

// Default edge options for animated black edges
const defaultEdgeOptions = {
  animated: true,
  style: {
    stroke: '#000000',
  },
};

console.log('Initial nodes:', initialNodes);
console.log('Initial edges:', initialEdges);
console.log('========= Window data ========');
console.log(window.data);


const FlowCanvas = ({ flowId = '1' }: FlowCanvasProps) => {
  const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);
  const [dataDrawerOpen, setDataDrawerOpen] = useState(false);
  const { toast } = useToast();

  const onConnect = useCallback(
    (params: Connection | Edge) => {
      const newEdge = {
        ...params,
        id: `e${Date.now()}`,
      };
      return setEdges((eds) => addEdge(newEdge, eds));
    },
    [setEdges],
  );

  const handleSave = async () => {
    const nodesWithData = nodes.map(node => ({
      ...node,
      data: {
        ...node.data,
        settings: node.data?.settings || {},
      },
    }));

    const flowData = {
      nodes: nodesWithData,
      edges,
    };
    
    console.log('Saving flow:', flowData);
    console.log('Flow ID:',window.data.flow.id);
    
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

      toast({
        title: "Flow saved",
        description: "Your flow has been saved successfully.",
      });
    } catch (error) {
      console.error('Error saving flow:', error);
      toast({
        title: "Save failed",
        description: "Failed to save flow. Please try again.",
        variant: "destructive",
      });
    }
  };

  const handlePopoverIndexChange = (index: number | null) => {
    // No longer automatically opening the data sidebar when Data popover is shown
  };
  
  const handleOpenDataSidebar = () => {
    setDataDrawerOpen(true);
  };

  const handleBackClick = () => {
    window.location.href = "/flows";
  };

  return (
    <div className="flex h-screen bg-[#F1F0FB] relative">
      {/* Left Sidebar */}
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

      {/* Main Flow Area */}
      <div className="flex-1 relative">
        {/* Back and Save Buttons */}
        <div className="absolute top-4 right-4 z-10 flex space-x-2">
          <Button 
            onClick={handleBackClick}
            variant="outline"
            size="default"
            className="gap-2 bg-white"
          >
            <ArrowLeft className="h-4 w-4" />
            Back
          </Button>
          <Button 
            onClick={handleSave}
            variant="outline"
            size="default"
            className="gap-2 bg-white"
          >
            <Save className="h-4 w-4" />
            Save
          </Button>
        </div>

        <ReactFlow
          nodes={nodes}
          edges={edges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          nodeTypes={nodeTypes}
          defaultEdgeOptions={defaultEdgeOptions}
          minZoom={0.1}
          maxZoom={4}
          fitView
        >
          <Background />
        </ReactFlow>

        {/* Data Sidebar */}
        <DataSidebar 
          open={dataDrawerOpen}
          onOpenChange={setDataDrawerOpen}
        />
      </div>
    </div>
  );
};

export default FlowCanvas;
