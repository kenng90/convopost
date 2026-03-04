import { useCallback } from 'react';
import { Node, useReactFlow } from '@xyflow/react';
// import { ActionType, NodeData, WebhookVariable } from '@/types/flow';

export const useFlowActions = () => {
  const { addNodes, deleteElements, getNodes, setViewport, getViewport, setNodes } = useReactFlow();

  const getRightmostPosition = () => {
    const nodes = getNodes();
    if (nodes.length === 0) return { x: 0, y: 100 };
    
    const rightmostNode = nodes.reduce((prev, current) => {
      return (prev.position.x > current.position.x) ? prev : current;
    });
    
    return {
      x: rightmostNode.position.x + 400,
      y: rightmostNode.position.y
    };
  };

  const updateNode = useCallback((nodeId: string, updates: any) => {
    setNodes((nodes) =>
      nodes.map((node) =>
        node.id === nodeId
          ? { ...node, data: { ...node.data, ...updates } }
          : node
      )
    );
  }, [setNodes]);

  const createNodeBase = useCallback((
    type: string,
    position: { x: number; y: number },
    data?: any
  ) => {
    const nodes = getNodes();
    const webhookNode = nodes.find(node => node.type === 'webhook') as Node<{ settings?: { webhookVariables?: any[] } }>;
    const webhookVariables = webhookNode?.data?.settings?.webhookVariables || [];

    const newPosition = getRightmostPosition();

    const newNode: Node = {
      id: `${type}-${Math.random()}`,
      type: type === 'incoming_message' ? 'incomingMessage' : 
            type === 'end' ? 'end' : 
            type === 'trigger' ? 'trigger' : 
            type === 'keyword_trigger' ? 'keyword_trigger' :
            type === 'opening_hours' ? 'opening_hours' :
            type === 'template' ? 'template' :
            type === 'webhook' ? 'webhook' :
            type === 'wait' ? 'wait' :
            type === 'http' ? 'http' :
            type === 'question' ? 'question' :
            type === 'image' ? 'image' :
            type === 'pdf' ? 'pdf' :
            type === 'video' ? 'video' :
            type === 'message' ? 'message' :
            type === 'quick_replies' ? 'quick_replies' :
            type === 'list_message' ? 'list_message' :
            type === 'openai' ? 'openai' :
            type === 'datastore' ? 'datastore' :
            type === 'assign_agent' ? 'assign_agent' :
            type === 'assign_group' ? 'assign_group' :
            type === 'counter' ? 'counter' :
            type === 'check_pricing' ? 'check_pricing' :
            type === 'branch' ? 'branch' : 'action',
      position: newPosition,
      data: data || {
        label: type === 'incoming_message' ? 'On Message' : 
               type === 'keyword_trigger' ? 'On Keyword' :
               type === 'wait' ? 'Wait' :
               type === 'http' ? 'HTTP Request' :
               type === 'question' ? 'Question' :
               type === 'image' ? 'Image' :
               type === 'pdf' ? 'PDF' :
               type === 'video' ? 'Video' :
               type === 'message' ? 'Message' :
               type === 'quick_replies' ? 'Quick Replies Message' :
               type === 'list_message' ? 'List Message' :
               type === 'openai' ? 'OpenAI' :
               type === 'datastore' ? 'Data Store' :
               type === 'assign_agent' ? 'Assign to Agent' :
               type === 'assign_group' ? 'Assign to Group' :
               type === 'counter' ? 'Counter' :
               type === 'check_pricing' ? 'Check User Pricing' :
               type.charAt(0).toUpperCase() + type.slice(1),
        type,
        settings: type === 'branch' 
          ? { webhookVariables }
          : type === 'wait'
          ? { waitTime: 0, waitUnit: 'seconds' }
          : type === 'http'
          ? { method: 'GET', url: '', headers: [], params: [] }
          : type === 'question'
          ? { question: '', variableName: '' }
          : type === 'quick_replies'
          ? { header: '', body: '', footer: '', activeButtons: 1, button1: '', button2: '', button3: '' }
          : type === 'list_message'
          ? { 
              header: '', 
              body: '', 
              footer: '', 
              buttonText: 'Choose an option', 
              sections: [
                {
                  id: 'section1',
                  title: 'Options',
                  rows: [
                    { id: 'row1', title: 'Option 1', description: 'Description for option 1' }
                  ]
                }
              ]
            }
          : type === 'openai'
          ? { openai: { model: 'gpt-4o-mini', prompt: '', systemPrompt: '', temperature: 0.7, maxTokens: 1000, responseHandling: 'reply' as const } }
          : type === 'datastore'
          ? { dataSource: '' }
          : type === 'assign_agent'
          ? { agentId: 'none' }
          : type === 'assign_group'
          ? { groupId: 'none', action: 'add' }
          : type === 'counter'
          ? { counter: { maxExecutions: 1, period: 'all_time' } }
          : type === 'check_pricing'
          ? { pricing: { freeExecutions: 0 } }
          : {},
      },
    };

    addNodes(newNode);

    // Center the viewport on the new node
    const viewport = getViewport();
    setViewport(
      { 
        x: -(newPosition.x * viewport.zoom - window.innerWidth / 2), 
        y: -(newPosition.y * viewport.zoom - window.innerHeight / 2), 
        zoom: viewport.zoom 
      },
      { duration: 800 }
    );

    return newNode;
  }, [addNodes, getNodes, getViewport, setViewport]);

  const createNodeKeyword = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('keyword_trigger', position, {
      keywords: [{ id: '1', value: '', matchType: 'exact' as const }]
    });
  }, [createNodeBase]);

  const createNodeQuickReply = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('quick_replies', position, {
      settings: {
        quickReplies: [],
        activeButtons: 1
      }
    });
  }, [createNodeBase]);

  const createNodeListMessage = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('list_message', position, {
      label: "List Message",
      type: "list_message",
      settings: {
        header: '',
        body: '',
        footer: '',
        buttonText: 'Choose an option',
        sections: [
          {
            id: 'section1',
            title: 'Options',
            rows: [
              { id: 'row1', title: 'Option 1', description: 'Description for option 1' }
            ]
          }
        ]
      }
    });
  }, [createNodeBase]);

  const createNodeMessage = useCallback((position: { x: number; y: number }, data?: any) => {
    return createNodeBase('message', position, {
      label: "Text Message",
      settings: {
        message: "",
        ...(data?.settings || {})
      }
    });
  }, [createNodeBase]);

  const createNodeTemplate = useCallback((position: { x: number; y: number }, data?: any) => {
    return createNodeBase('template', position, {
      label: "Template Message",
      type: "template",
      settings: {
        templateId: "",
        parameters: {}
      }
    });
  }, [createNodeBase]);

  const createNodeWebhook = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('webhook', position, {
      settings: {
        webhookVariables: []
      }
    });
  }, [createNodeBase]);

  const createNodeIncomingMessage = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('incoming_message', position);
  }, [createNodeBase]);

  const createNodeEnd = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('end', position);
  }, [createNodeBase]);

  const createNodeOpenAI = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('openai', position, {
      label: "OpenAI",
      type: "openai",
      settings: {
        openai: {
          model: 'gpt-4o-mini',
          prompt: '',
          systemPrompt: '',
          temperature: 0.7,
          maxTokens: 1000,
          responseHandling: 'reply' as const,
          variableName: ''
        }
      }
    });
  }, [createNodeBase]);

  const deleteNode = useCallback((nodeId: string) => {
    deleteElements({ nodes: [{ id: nodeId }] });
  }, [deleteElements]);

  const createNodeOpeningHours = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('opening_hours', position, {
      settings: {
        timeRanges: [
          { day: 'Monday', start: '09:00', end: '17:00' }
        ]
      }
    });
  }, [createNodeBase]);

  const createNodeHTTP = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('http', position, {
      settings: {
        http: {
          method: 'GET',
          url: '',
          headers: [],
          params: []
        }
      }
    });
  }, [createNodeBase]);

  const createNodeAssignAgent = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('assign_agent', position, {
      label: "Assign to Agent",
      type: "assign_agent",
      settings: {
        agentId: 'none'
      }
    });
  }, [createNodeBase]);

  const createNodeAssignGroup = useCallback((position: { x: number; y: number }) => {
    return createNodeBase('assign_group', position, {
      label: "Assign to Group",
      type: "assign_group",
      settings: {
        groupId: 'none',
        action: 'add'
      }
    });
  }, [createNodeBase]);

  return {
    createNodeBase,
    createNodeKeyword,
    createNodeQuickReply,
    createNodeListMessage,
    createNodeMessage,
    createNodeTemplate,
    createNodeWebhook,
    createNodeIncomingMessage,
    createNodeEnd,
    createNodeOpeningHours,
    createNodeHTTP,
    createNodeOpenAI,
    createNodeAssignAgent,
    createNodeAssignGroup,
    deleteNode,
    updateNode
  };
};
