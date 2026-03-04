
import TriggerNode from '@/components/flow/TriggerNode';
import ActionNode from '@/components/flow/ActionNode';
import EndNode from '@/components/flow/EndNode';
import IncomingMessageNode from '@/components/flow/IncomingMessageNode';
import KeywordTriggerNode from '@/components/flow/KeywordTriggerNode';
import OpeningHoursNode from '@/components/flow/OpeningHoursNode';
import TemplateNode from '@/components/flow/TemplateNode';
import WebhookNode from '@/components/flow/WebhookNode';
import BranchNode from '@/components/flow/BranchNode';
import WaitNode from '@/components/flow/WaitNode';
import HTTPNode from '@/components/flow/HTTPNode';
import MessageNode from '@/components/flow/MessageNode';
import MediaNode from '@/components/flow/MediaNode';
import QuestionNode from '@/components/flow/QuestionNode';
import ImageNode from '@/components/flow/ImageNode';
import PDFNode from '@/components/flow/PDFNode';
import VideoNode from '@/components/flow/VideoNode';
import QuickRepliesNode from '@/components/flow/QuickRepliesNode';
import ListMessageNode from '@/components/flow/ListMessageNode';
import OpenAINode from '@/components/flow/OpenAINode';
import DataStoreNode from '@/components/flow/DataStoreNode';
import AssignAgentNode from '@/components/flow/AssignAgentNode';
import AssignGroupNode from '@/components/flow/AssignGroupNode';
import CounterNode from '@/components/flow/CounterNode';
import CheckPricingNode from '@/components/flow/CheckPricingNode';

export const nodeTypes = {
  trigger: TriggerNode,
  action: ActionNode,
  end: EndNode,
  incomingMessage: IncomingMessageNode,
  keyword_trigger: KeywordTriggerNode,
  opening_hours: OpeningHoursNode,
  template: TemplateNode,
  webhook: WebhookNode,
  branch: BranchNode,
  wait: WaitNode,
  http: HTTPNode,
  message: MessageNode,
  media: MediaNode,
  question: QuestionNode,
  image: ImageNode,
  pdf: PDFNode,
  video: VideoNode,
  quick_replies: QuickRepliesNode,
  list_message: ListMessageNode,
  openai: OpenAINode,
  datastore: DataStoreNode,
  assign_agent: AssignAgentNode,
  assign_group: AssignGroupNode,
  counter: CounterNode,
  check_pricing: CheckPricingNode,
};
