import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Plus, Trash2 } from 'lucide-react';
import { VariableInput } from '../../common/VariableInput';

interface ParametersSectionProps {
  params: Array<{ id: string; key: string; value: string; }>;
  onAddParam: () => void;
  onUpdateParam: (id: string, key: string, value: string) => void;
  onRemoveParam: (id: string) => void;
}

const ParametersSection = ({ params, onAddParam, onUpdateParam, onRemoveParam }: ParametersSectionProps) => {
  return (
    <div>
      <div className="flex items-center justify-between mb-2">
        <Label>Parameters</Label>
        <Button variant="outline" size="sm" onClick={onAddParam}>
          <Plus className="h-4 w-4 mr-2" />
          Add Parameter
        </Button>
      </div>
      <div className="space-y-2">
        {params.length === 0 ? (
          <p className="text-xs text-muted-foreground border border-dashed rounded-md p-3">
            Add parameters to send with the request. After a WhatsApp Form node, use{' '}
            <code className="text-xs bg-muted px-1 rounded">{'{{whatsapp_flow_responses}}'}</code>{' '}
            for all answers (JSON), or <code className="text-xs bg-muted px-1 rounded">{'{{form_<field>}}'}</code> per field.
          </p>
        ) : null}
        {params.map((param) => (
          <div key={param.id} className="flex gap-2">
            <Input
              placeholder="Key (e.g. responses)"
              value={param.key}
              onChange={(e) => onUpdateParam(param.id, e.target.value, param.value)}
              className="flex-1"
            />
            <VariableInput
              placeholder="{{whatsapp_flow_responses}}"
              value={param.value}
              onChange={(value) => onUpdateParam(param.id, param.key, value)}
              className="flex-1"
            />
            <Button
              variant="ghost"
              size="icon"
              onClick={() => onRemoveParam(param.id)}
            >
              <Trash2 className="h-4 w-4 text-red-500" />
            </Button>
          </div>
        ))}
      </div>
    </div>
  );
};

export default ParametersSection;