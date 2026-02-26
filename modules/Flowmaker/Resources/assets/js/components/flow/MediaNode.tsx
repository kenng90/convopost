
import { Image, FileText, Music, Video } from "lucide-react";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { NodeData } from "@/types/flow";
import BaseNodeLayout from "./BaseNodeLayout";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";

interface MediaNodeProps {
  id: string;
  data: NodeData;
  isConnectable: boolean;
}

const mediaTypeIcons = {
  image: Image,
  video: Video,
  audio: Music,
  document: FileText,
};

const MediaNode = ({ id, data, isConnectable }: MediaNodeProps) => {
  const mediaType = data.settings?.media?.type || "image";
  const MediaIcon = mediaTypeIcons[mediaType as keyof typeof mediaTypeIcons];

  return (
    <BaseNodeLayout
      id={id}
      title="Basic Image Generate"
      icon={<MediaIcon className="h-4 w-4 text-blue-600" />}
      headerBackground="bg-blue-50"
      borderColor="border-blue-200"
      nodeWidth="w-[350px]"
    >
      <div className="space-y-4">
        <div className="space-y-2">
          <Label>Prompt</Label>
          <Textarea
            placeholder="Enter the prompt..."
            className="min-h-[80px] bg-gray-50"
          />
        </div>

        <div className="space-y-2">
          <Label>Choose AI Model</Label>
          <div className="flex gap-2">
            <Button variant="outline" className="bg-gray-900 text-white hover:bg-gray-800 px-3 py-1 h-auto text-xs rounded-full">
              + Add models
            </Button>
            <Button variant="outline" className="bg-white text-gray-700 border-gray-300 hover:bg-gray-50 px-3 py-1 h-auto text-xs rounded-full">
              Flux 11 Pro
            </Button>
          </div>
        </div>

        <div className="space-y-2">
          <Label>Aspect Ratio</Label>
          <Select defaultValue="16:9">
            <SelectTrigger className="bg-gray-50">
              <SelectValue placeholder="Select aspect ratio" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="16:9">16:9</SelectItem>
              <SelectItem value="4:3">4:3</SelectItem>
              <SelectItem value="1:1">1:1</SelectItem>
              <SelectItem value="9:16">9:16</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </BaseNodeLayout>
  );
};

export default MediaNode;
