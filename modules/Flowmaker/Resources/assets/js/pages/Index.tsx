
import { ReactFlowProvider } from '@xyflow/react';
import FlowCanvas from '@/components/flow/FlowCanvas';
import '@xyflow/react/dist/style.css';

const Index = (props) => {
  return (
    <ReactFlowProvider>
      <FlowCanvas />
    </ReactFlowProvider>
  );
};

export default Index;
