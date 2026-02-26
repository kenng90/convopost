import { Handle, Position } from '@xyflow/react';
import { Image as ImageIcon, Trash2, Upload } from 'lucide-react';
import { useFlowActions } from "@/hooks/useFlowActions";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { useCallback, useState } from 'react';
import { useReactFlow, Node } from '@xyflow/react';
import { Button } from "@/components/ui/button";
import { NodeData } from "@/types/flow";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { VariableTextArea } from '../common/VariableTextArea';

interface ImageNodeProps {
  id: string;
  data: NodeData;
}

const ImageNode = ({ id, data }: ImageNodeProps) => {
  const { deleteNode } = useFlowActions();
  const { setNodes } = useReactFlow();
  const [isUploading, setIsUploading] = useState(false);
  const [caption, setCaption] = useState(data.settings?.caption || '');
  const [uploadError, setUploadError] = useState<string | null>(null);

  const handleImageUpload = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    setUploadError(null);
    
    // Create a FormData object and append the file
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', 'image');

    // Make a POST request to the server
    fetch('/flowmakermedia', {
      method: 'POST',
      body: formData,
    })
      .then(response => response.json())
      .then(data => {
        if (data.error) {
          throw new Error(data.error);
        }
        
        setNodes((nodes) =>
          nodes.map((node: Node<NodeData>) => {
            if (node.id === id) {
              const settings = node.data.settings || {};
              return {
                ...node,
                data: {
                  ...node.data,
                  settings: {
                    ...settings,
                    imageUrl: data.url,
                  },
                },
              };
            }
            return node;
          })
        );
        setIsUploading(false);
      })
      .catch(error => {
        console.error('Error uploading image:', error);
        setUploadError(error.message || 'Failed to upload image');
        setIsUploading(false);
      });
  }, [id, setNodes]);

  const handleCaptionChange = useCallback((value: string) => {
    setCaption(value);
    setNodes((nodes) =>
      nodes.map((node: Node<NodeData>) => {
        if (node.id === id) {
          const settings = node.data.settings || {};
          return {
            ...node,
            data: {
              ...node.data,
              settings: {
                ...settings,
                caption: value,
              },
            },
          };
        }
        return node;
      })
    );
  }, [id, setNodes]);

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="w-[300px] bg-white rounded-lg shadow-lg">
          <Handle type="target" position={Position.Left} className="!bg-gray-300 !w-3 !h-3 !rounded-full" />
          
          <div className="flex items-center gap-2 border-b border-gray-100 px-3 py-2 bg-gray-50">
            <ImageIcon className="h-4 w-4 text-purple-600 flex-shrink-0" />
            <div className="font-medium leading-none">Send Image</div>
          </div>

          <div className="p-4 space-y-4">
            <div className="space-y-2">
              {data.settings?.imageUrl ? (
                <div className="relative group">
                  <img 
                    src={data.settings.imageUrl} 
                    alt="Uploaded preview" 
                    className="w-full h-[200px] object-cover rounded-md"
                  />
                  <div className="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={() => {
                        const input = document.getElementById(`image-upload-${id}`) as HTMLInputElement;
                        input?.click();
                      }}
                    >
                      Change Image
                    </Button>
                  </div>
                </div>
              ) : (
                <div 
                  className="border-2 border-dashed border-gray-200 rounded-md p-8 text-center cursor-pointer hover:border-gray-300 transition-colors"
                  onClick={() => {
                    const input = document.getElementById(`image-upload-${id}`) as HTMLInputElement;
                    input?.click();
                  }}
                >
                  <Upload className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                  <div className="text-sm text-gray-500">
                    Click to upload an image
                  </div>
                </div>
              )}
              <input
                type="file"
                id={`image-upload-${id}`}
                className="hidden"
                accept="image/*"
                onChange={handleImageUpload}
                disabled={isUploading}
              />
              {isUploading && (
                <div className="text-sm text-center text-blue-600">
                  Uploading image...
                </div>
              )}
              {uploadError && (
                <div className="text-sm text-center text-red-600">
                  {uploadError}
                </div>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="caption">Caption</Label>
              <VariableTextArea
                value={data.settings?.caption || ""}
                onChange={handleCaptionChange}
                placeholder="Add a caption..."
              />
            </div>
          </div>

          <Handle type="source" position={Position.Right} className="!bg-gray-300 !w-3 !h-3 !rounded-full" />
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

export default ImageNode;
