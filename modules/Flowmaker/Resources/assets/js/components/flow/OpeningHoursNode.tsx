import React, { useState, useEffect } from 'react';
import { Handle, Position, useReactFlow, Node } from '@xyflow/react';
import { Button } from "@/components/ui/button";
import { Clock, Plus, X } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { TimeRange, NodeData } from '@/types/flow';

const daysOfWeek = [
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
  'Sunday'
];

interface OpeningHoursNodeProps {
  data: NodeData;
  id: string;
}

const OpeningHoursNode = ({ data, id }: OpeningHoursNodeProps) => {
  const { setNodes } = useReactFlow();
  const [timeRanges, setTimeRanges] = useState<TimeRange[]>(
    data.settings?.timeRanges || []
  );

  useEffect(() => {
    setNodes((nodes: Node[]) => 
      nodes.map((node: Node<NodeData>) => {
        if (node.id === id) {
          return {
            ...node,
            data: {
              ...node.data,
              settings: {
                ...node.data.settings,
                timeRanges
              }
            }
          };
        }
        return node;
      })
    );
  }, [timeRanges, id, setNodes]);

  const addTimeRange = () => {
    setTimeRanges([
      ...timeRanges,
      { day: daysOfWeek[0], start: '09:00', end: '17:00' }
    ]);
  };

  const removeTimeRange = (index: number) => {
    setTimeRanges(timeRanges.filter((_, i) => i !== index));
  };

  const updateTimeRange = (index: number, field: keyof TimeRange, value: string) => {
    const newTimeRanges = [...timeRanges];
    newTimeRanges[index] = {
      ...newTimeRanges[index],
      [field]: value
    };
    setTimeRanges(newTimeRanges);
  };

  return (
    <div className="bg-white rounded-lg shadow-lg w-[350px]">
      <Handle
        type="target"
        position={Position.Left}
        style={{ left: '-4px', background: '#555' }}
      />
      
      <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
        <Clock className="h-4 w-4 text-orange-600" />
        <div className="font-medium">Opening Hours</div>
      </div>

      <div className="space-y-4">
        {timeRanges.map((range, index) => (
          <div key={index} className="flex gap-2 items-end">
            <div className="flex-1">
              <label className="text-sm text-gray-500">Day</label>
              <Select
                value={range.day}
                onValueChange={(value) => updateTimeRange(index, 'day', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select day" />
                </SelectTrigger>
                <SelectContent>
                  {daysOfWeek.map((day) => (
                    <SelectItem key={day} value={day}>
                      {day}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex-1">
              <label className="text-sm text-gray-500">Opens</label>
              <Select
                value={range.start}
                onValueChange={(value) => updateTimeRange(index, 'start', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Start time" />
                </SelectTrigger>
                <SelectContent>
                  {Array.from({ length: 24 }).map((_, i) => (
                    <SelectItem key={i} value={`${i.toString().padStart(2, '0')}:00`}>
                      {`${i.toString().padStart(2, '0')}:00`}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex-1">
              <label className="text-sm text-gray-500">Closes</label>
              <Select
                value={range.end}
                onValueChange={(value) => updateTimeRange(index, 'end', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="End time" />
                </SelectTrigger>
                <SelectContent>
                  {Array.from({ length: 24 }).map((_, i) => (
                    <SelectItem key={i} value={`${i.toString().padStart(2, '0')}:00`}>
                      {`${i.toString().padStart(2, '0')}:00`}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => removeTimeRange(index)}
              className="mb-[2px]"
            >
              <X className="w-4 h-4" />
            </Button>
          </div>
        ))}
      </div>

      <Handle
        type="source"
        position={Position.Right}
        id="open"
        style={{ right: '-4px', top: '30%', background: '#22c55e' }}
      />
      <Handle
        type="source"
        position={Position.Right}
        id="closed"
        style={{ right: '-4px', top: '70%', background: '#ef4444' }}
      />
    </div>
  );
};

export default OpeningHoursNode;
