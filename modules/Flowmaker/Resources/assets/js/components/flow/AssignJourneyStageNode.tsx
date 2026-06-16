import React, { useMemo } from 'react';
import BaseNodeLayout from './BaseNodeLayout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { useFlowActions } from '../../hooks/useFlowActions';

interface AssignJourneyStageNodeProps {
  id: string;
  data: any;
  selected: boolean;
}

const AssignJourneyStageNode: React.FC<AssignJourneyStageNodeProps> = ({ id, data }) => {
  const journeys = (window as any).data?.journeys || [];
  const { updateNode } = useFlowActions();

  const journeyId = data.settings?.journeyId;
  const stageId = data.settings?.stageId;

  const selectedJourney = useMemo(
    () => journeys.find((journey: any) => journey.id.toString() === journeyId?.toString()),
    [journeys, journeyId]
  );

  const stages = selectedJourney?.stages || [];
  const selectedStage = stages.find((stage: any) => stage.id.toString() === stageId?.toString());

  const handleJourneyChange = (value: string) => {
    updateNode(id, {
      settings: {
        ...data.settings,
        journeyId: value,
        stageId: 'none',
      },
    });
  };

  const handleStageChange = (value: string) => {
    updateNode(id, {
      settings: {
        ...data.settings,
        stageId: value,
      },
    });
  };

  return (
    <BaseNodeLayout
      id={id}
      title="Move to Journey Stage"
      icon="🧭"
      headerBackground="bg-purple-50"
      borderColor="border-purple-200"
    >
      <div className="space-y-3">
        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">Journey</label>
          <Select value={journeyId?.toString() || ''} onValueChange={handleJourneyChange}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Select journey" />
            </SelectTrigger>
            <SelectContent>
              {journeys.map((journey: any) => (
                <SelectItem key={journey.id} value={journey.id.toString()}>
                  {journey.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">Stage</label>
          <Select value={stageId?.toString() || ''} onValueChange={handleStageChange}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Select stage" />
            </SelectTrigger>
            <SelectContent>
              {stages.map((stage: any) => (
                <SelectItem key={stage.id} value={stage.id.toString()}>
                  {stage.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {selectedStage && (
          <div className="text-sm text-gray-600 mt-2">
            <div className="font-medium text-purple-700">
              Move to: {selectedJourney?.name} → {selectedStage.name}
            </div>
          </div>
        )}
      </div>
    </BaseNodeLayout>
  );
};

export default AssignJourneyStageNode;
