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
import WhatsAppCatalogNode from '@/components/flow/WhatsAppCatalogNode';
import ListingInquiryNode from '@/components/flow/ListingInquiryNode';
import WhatsAppFlowNode from '@/components/flow/WhatsAppFlowNode';
import OpenAINode from '@/components/flow/OpenAINode';
import DataStoreNode from '@/components/flow/DataStoreNode';
import AssignAgentNode from '@/components/flow/AssignAgentNode';
import AssignGroupNode from '@/components/flow/AssignGroupNode';
import AssignJourneyStageNode from '@/components/flow/AssignJourneyStageNode';
import CounterNode from '@/components/flow/CounterNode';
import CheckPricingNode from '@/components/flow/CheckPricingNode';
import MpesaStkPushNode from '@/components/flow/MpesaStkPushNode';
import RequestPaymentNode from '@/components/flow/RequestPaymentNode';
import CatalogSearchNode from '@/components/flow/CatalogSearchNode';
import BookAppointmentNode from '@/components/flow/BookAppointmentNode';
import BookingEventsListNode from '@/components/flow/BookingEventsListNode';
import BookingEventRegisterNode from '@/components/flow/BookingEventRegisterNode';
import SendBookingLinkNode from '@/components/flow/SendBookingLinkNode';
import ManageBookingNode from '@/components/flow/ManageBookingNode';

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
  whatsapp_catalog: WhatsAppCatalogNode,
  catalog_search: CatalogSearchNode,
  listing_inquiry: ListingInquiryNode,
  whatsapp_flow: WhatsAppFlowNode,
  openai: OpenAINode,
  datastore: DataStoreNode,
  assign_agent: AssignAgentNode,
  assign_group: AssignGroupNode,
  assign_journey_stage: AssignJourneyStageNode,
  counter: CounterNode,
  check_pricing: CheckPricingNode,
  mpesa_stk_push: MpesaStkPushNode,
  request_payment: RequestPaymentNode,
  book_appointment: BookAppointmentNode,
  booking_events_list: BookingEventsListNode,
  booking_event_register: BookingEventRegisterNode,
  send_booking_link: SendBookingLinkNode,
  manage_booking: ManageBookingNode,
};
