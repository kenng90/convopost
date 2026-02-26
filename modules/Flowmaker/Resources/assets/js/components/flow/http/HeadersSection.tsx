import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Plus, Trash2 } from 'lucide-react';
import { VariableInput } from '../../common/VariableInput';

interface HeadersSectionProps {
  headers: Array<{ id: string; key: string; value: string; }>;
  onAddHeader: () => void;
  onUpdateHeader: (id: string, key: string, value: string) => void;
  onRemoveHeader: (id: string) => void;
}

const HeadersSection = ({ headers, onAddHeader, onUpdateHeader, onRemoveHeader }: HeadersSectionProps) => {
  return (
    <div>
      <div className="flex items-center justify-between mb-2">
        <Label>Headers</Label>
        <Button variant="outline" size="sm" onClick={onAddHeader}>
          <Plus className="h-4 w-4 mr-2" />
          Add Header
        </Button>
      </div>
      <div className="space-y-2">
        {headers.map((header) => (
          <div key={header.id} className="flex gap-2">
            <Input
              placeholder="Key"
              value={header.key}
              onChange={(e) => onUpdateHeader(header.id, e.target.value, header.value)}
              className="flex-1"
            />
            <VariableInput
              placeholder="Value"
              value={header.value}
              onChange={(value) => onUpdateHeader(header.id, header.key, value)}
              className="flex-1"
            />
            <Button
              variant="ghost"
              size="icon"
              onClick={() => onRemoveHeader(header.id)}
            >
              <Trash2 className="h-4 w-4 text-red-500" />
            </Button>
          </div>
        ))}
      </div>
    </div>
  );
};

export default HeadersSection;