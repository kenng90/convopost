
export type NodeType = 'trigger' | 'action' | 'end' | 'incomingMessage' | 'keyword_trigger' | 'opening_hours' | 'template' | 'webhook' | 'branch' | 'http' | 'media' | 'question' | 'image' | 'pdf' | 'video' | 'openai' | 'datastore' | 'counter' | 'check_pricing';

export type ActionType = 'message' | 'wait' | 'branch' | 'end' | 'trigger' | 'incoming_message' | 'keyword_trigger' | 'quick_replies' | 'opening_hours' | 'template' | 'webhook' | 
  'assign_agent' | 'assign_team' | 'unassign_agent' | 'unassign_team' | 'internal_note' | 'contact_label' | 'tag' | 'add_contact' | 'remove_contact' | 'archive' | 'unarchive' | 'update_contact' | 'http' | 'media' | 'question' | 'image' | 'pdf' | 'video' | 'openai' | 'datastore' | 'counter' | 'check_pricing';

export type MessageType = 'text' | 'media' | 'template' | 'interactive' | 'quick_reply' | 'list';
export type TriggerType = 'incoming_message' | 'keyword' | 'opening_hours' | 'template' | 'webhook';

export interface TimeRange {
  day: string;
  start: string;
  end: string;
}

export interface WebhookVariable {
  id: string;
  name: string;
  type: 'string' | 'number' | 'boolean';
  required: boolean;
}

export interface BranchCondition {
  id: string;
  variableId: string;
  operator: 'equals' | 'not_equals' | 'greater_than' | 'less_than' | 'contains' | 'not_contains';
  value: string;
}

export interface NodeData {
  label?: string;
  type?: ActionType;
  messageType?: MessageType;
  triggerType?: TriggerType;
  id?: string;
  keywords?: Array<{
    id: string;
    value: string;
    matchType: 'exact' | 'contains';
  }>;
  settings?: {
    message?: string;
    caption?: string;
    question?: string;
    variableName?: string;
    imageUrl?: string;
    pdfUrl?: string;
    videoUrl?: string;
    waitTime?: number;
    waitUnit?: 'seconds' | 'minutes' | 'hours' | 'days';
    header?: string;
    body?: string;
    footer?: string;
    activeButtons?: number;
    button1?: string;
    button2?: string;
    button3?: string;
    media?: {
      type: 'image' | 'video' | 'audio' | 'document';
      url: string;
    };
    template?: {
      name: string;
      language: string;
      component?: {
        buttons?: {
          buttons: Array<{ text: string }>;
        };
      };
    };
    templateId?: string;
    parameters?: Record<string, string>;
    interactive?: {
      type: string;
      content: any;
    };
    quickReplies?: string[];
    list?: {
      title: string;
      items: string[];
    };
    keyword?: {
      text: string;
      matchType: 'exact' | 'contains';
    };
    timeRanges?: TimeRange[];
    webhook?: {
      url?: string;
      variables?: WebhookVariable[];
    };
    webhookVariables?: WebhookVariable[];
    branchConditions?: BranchCondition[];
    conditions?: BranchCondition[];
    actionType?: ActionType;
    http?: {
      method: 'GET' | 'POST' | 'PUT' | 'DELETE';
      url: string;
      headers: Array<{ id: string; key: string; value: string; }>;
      params: Array<{ id: string; key: string; value: string; }>;
    };
    openai?: {
      model: 'gpt-4o-mini' | 'gpt-4o';
      prompt: string;
      systemPrompt: string;
      temperature: number;
      maxTokens: number;
      responseHandling: 'reply' | 'store';
      variableName?: string;
    };
    dataSource?: string;
    dataStore?: {
      name?: string;
      type?: 'database' | 'api' | 'file';
      connectionDetails?: Record<string, string>;
    };
    counter?: {
      maxExecutions: number;
      period: 'all_time' | 'last_30_days';
    };
    pricing?: {
      freeExecutions: number;
    };
  };
  [key: string]: any;
}
