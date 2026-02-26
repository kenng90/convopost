import { Button } from "@/components/ui/button";
import { MessageSquare, Clock, GitMerge, Square, Variable, Terminal, Plug, Network } from "lucide-react";

const Sidebar = () => {
  const onDragStart = (event: React.DragEvent, nodeType: string, label: string) => {
    event.dataTransfer.setData('application/reactflow', JSON.stringify({ type: nodeType, label }));
    event.dataTransfer.effectAllowed = 'move';
  };

  return (
    <div className="w-64 bg-white/80 backdrop-blur-sm border-r border-gray-200 p-6 shadow-lg">
      <h2 className="text-lg font-semibold mb-6 text-gray-800">Flow Nodes</h2>
      <div className="space-y-3">
        <div
          className="p-3 border rounded-xl cursor-move bg-whatsapp-green shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
          onDragStart={(e) => onDragStart(e, 'trigger', 'New Message')}
          draggable
        >
          <div className="flex items-center gap-3">
            <MessageSquare size={18} />
            <span className="font-medium">Message Trigger</span>
          </div>
        </div>
        <div
          className="p-3 border rounded-xl cursor-move bg-whatsapp-lightblue shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
          onDragStart={(e) => onDragStart(e, 'action', 'Wait')}
          draggable
        >
          <div className="flex items-center gap-3">
            <Clock size={18} />
            <span className="font-medium">Wait</span>
          </div>
        </div>
        <div
          className="p-3 border rounded-xl cursor-move bg-whatsapp-lightblue shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
          onDragStart={(e) => onDragStart(e, 'action', 'Branch')}
          draggable
        >
          <div className="flex items-center gap-3">
            <GitMerge size={18} />
            <span className="font-medium">Logic Branch</span>
          </div>
        </div>
        <div
          className="p-3 border rounded-xl cursor-move bg-whatsapp-blue shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
          onDragStart={(e) => onDragStart(e, 'end', 'End Flow')}
          draggable
        >
          <div className="flex items-center gap-3">
            <Square size={18} />
            <span className="font-medium">End Flow</span>
          </div>
        </div>

        <div className="pt-6">
          <h2 className="text-lg font-semibold mb-4 text-gray-800">AI & Integrations</h2>
          <div className="space-y-3">
            <div
              className="p-3 border rounded-xl cursor-move bg-violet-100 shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
              onDragStart={(e) => onDragStart(e, 'voiceflow', 'VoiceFlow AI')}
              draggable
            >
              <div className="flex items-center gap-3">
                <Network size={18} className="text-violet-600" />
                <span className="font-medium">VoiceFlow AI</span>
              </div>
            </div>
            <div
              className="p-3 border rounded-xl cursor-move bg-indigo-100 shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
              onDragStart={(e) => onDragStart(e, 'flowise', 'FlowiseAI')}
              draggable
            >
              <div className="flex items-center gap-3">
                <Plug size={18} className="text-indigo-600" />
                <span className="font-medium">FlowiseAI</span>
              </div>
            </div>
            <div
              className="p-3 border rounded-xl cursor-move bg-sky-100 shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
              onDragStart={(e) => onDragStart(e, 'http', 'HTTP')}
              draggable
            >
              <div className="flex items-center gap-3">
                <Network size={18} className="text-sky-600" />
                <span className="font-medium">HTTP</span>
              </div>
            </div>
          </div>
        </div>

        <div className="pt-6">
          <h2 className="text-lg font-semibold mb-4 text-gray-800">Variables</h2>
          <div className="space-y-3">
            <div
              className="p-3 border rounded-xl cursor-move bg-whatsapp-purple shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
              onDragStart={(e) => onDragStart(e, 'variable', 'Variable')}
              draggable
            >
              <div className="flex items-center gap-3">
                <Variable size={18} />
                <span className="font-medium">Variables</span>
              </div>
            </div>
            <div
              className="p-3 border rounded-xl cursor-move bg-whatsapp-purple shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
              onDragStart={(e) => onDragStart(e, 'function', 'Function')}
              draggable
            >
              <div className="flex items-center gap-3">
                <Terminal size={18} />
                <span className="font-medium">Functions</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Sidebar;
