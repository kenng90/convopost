
import React from 'react';
import { Handle, Position } from '@xyflow/react';
import { ContextMenu, ContextMenuContent, ContextMenuItem, ContextMenuTrigger } from "@/components/ui/context-menu";
import { Trash2 } from 'lucide-react';
import { useFlowActions } from "@/hooks/useFlowActions";

interface BaseNodeLayoutProps {
  id: string;
  title: string;
  icon?: React.ReactNode;
  headerBackground?: string;
  borderColor?: string;
  children: React.ReactNode;
  nodeWidth?: string;
  titleColor?: string;
  className?: string;
  handles?: {
    left?: boolean;
    right?: boolean;
    top?: boolean;
    bottom?: boolean;
  };
}

const BaseNodeLayout = ({ 
  id, 
  title, 
  icon, 
  headerBackground = 'bg-gray-50', 
  borderColor = 'border-gray-100', 
  children,
  nodeWidth = 'w-[300px]',
  titleColor = 'text-gray-800',
  className = '',
  handles = { left: true, right: true }
}: BaseNodeLayoutProps) => {
  const { deleteNode } = useFlowActions();

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className={`${nodeWidth} rounded-lg border bg-white shadow-md overflow-hidden ${borderColor} ${className}`}>
          <div className={`flex items-center gap-2 px-3 py-2 border-b ${borderColor} ${headerBackground}`}>
            {icon && <div className="flex-shrink-0">{icon}</div>}
            <div className={`font-medium text-sm ${titleColor}`}>{title}</div>
          </div>
          
          <div className="p-4">
            {children}
          </div>

          {handles.left && (
            <Handle 
              type="target" 
              position={Position.Left}
              className="!bg-green-400 !w-3 !h-3 !rounded-full !border-2 !border-white"
              style={{ left: -6 }}
            />
          )}
          
          {handles.right && (
            <Handle 
              type="source" 
              position={Position.Right}
              className="!bg-green-400 !w-3 !h-3 !rounded-full !border-2 !border-white"
              style={{ right: -6 }}
            />
          )}
          
          {handles.top && (
            <Handle 
              type="target" 
              position={Position.Top}
              className="!bg-green-400 !w-3 !h-3 !rounded-full !border-2 !border-white"
              style={{ top: -6 }}
            />
          )}
          
          {handles.bottom && (
            <Handle 
              type="source" 
              position={Position.Bottom}
              className="!bg-green-400 !w-3 !h-3 !rounded-full !border-2 !border-white"
              style={{ bottom: -6 }}
            />
          )}
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

export default BaseNodeLayout;
