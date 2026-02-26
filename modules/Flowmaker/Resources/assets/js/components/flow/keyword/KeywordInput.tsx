
import { Trash2 } from 'lucide-react';
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Handle, Position } from '@xyflow/react';

interface KeywordInputProps {
  id: string;
  value: string;
  matchType: 'exact' | 'contains';
  index: number;
  totalKeywords: number;
  onUpdate: (id: string, field: 'value' | 'matchType', value: string) => void;
  onRemove: (id: string) => void;
}

const KeywordInput = ({ 
  id, 
  value, 
  matchType, 
  index,
  totalKeywords,
  onUpdate, 
  onRemove 
}: KeywordInputProps) => {
  return (
    <div className="flex items-center gap-2 relative group">
      <Input
        value={value}
        onChange={(e) => onUpdate(id, 'value', e.target.value)}
        placeholder="Enter keyword"
        className="flex-1"
      />
      <Select
        value={matchType}
        onValueChange={(value) => onUpdate(id, 'matchType', value)}
      >
        <SelectTrigger className="w-[140px]">
          <SelectValue placeholder="Exact match" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="exact">Exact match</SelectItem>
          <SelectItem value="contains">Contains</SelectItem>
        </SelectContent>
      </Select>
      <Button
        variant="ghost"
        size="icon"
        onClick={() => onRemove(id)}
        className="text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
      >
        <Trash2 className="h-4 w-4" />
      </Button>
      <Handle
        type="source"
        position={Position.Right}
        id={`keyword-${id}`}
        style={{ 
          right: '-4px',
          background: '#555'
        }}
      />
    </div>
  );
};

export default KeywordInput;
