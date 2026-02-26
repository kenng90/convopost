
import { MessageCircle, Image, FileText, MessageSquare, FileVideo, File, List } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useFlowActions } from "@/hooks/useFlowActions";
import { NodeData } from "@/types/flow";

interface MessagingSectionProps {
  searchQuery: string;
}

const messagingActions = [
  {
    icon: MessageCircle,
    label: "Send text message",
    bgColor: "bg-blue-100",
    textColor: "text-blue-600",
    onClick: (actions: ReturnType<typeof useFlowActions>) => {
      const position = { x: 250, y: 100 };
      const data: NodeData = {
        label: "Send Message",
        type: "message",
        settings: {
          message: ""
        }
      };
      return actions.createNodeBase('message', position, data);
    }
  },
  {
    icon: Image,
    label: "Send image",
    bgColor: "bg-purple-100",
    textColor: "text-purple-600",
    onClick: (actions: ReturnType<typeof useFlowActions>) => {
      const position = { x: 250, y: 100 };
      const data: NodeData = {
        label: "Send Image",
        type: "image",
        settings: {
          imageUrl: ""
        }
      };
      return actions.createNodeBase('image', position, data);
    }
  },
  {
    icon: File,
    label: "Send PDF",
    bgColor: "bg-red-100",
    textColor: "text-red-600",
    onClick: (actions: ReturnType<typeof useFlowActions>) => {
      const position = { x: 250, y: 100 };
      const data: NodeData = {
        label: "Send PDF",
        type: "pdf",
        settings: {
          pdfUrl: ""
        }
      };
      return actions.createNodeBase('pdf', position, data);
    }
  },
  {
    icon: FileVideo,
    label: "Send video",
    bgColor: "bg-indigo-100",
    textColor: "text-indigo-600",
    onClick: (actions: ReturnType<typeof useFlowActions>) => {
      const position = { x: 250, y: 100 };
      const data: NodeData = {
        label: "Send Video",
        type: "video",
        settings: {
          videoUrl: ""
        }
      };
      return actions.createNodeBase('video', position, data);
    }
  },
  {
    icon: FileText,
    label: "Send template",
    bgColor: "bg-indigo-100",
    textColor: "text-indigo-600",
    onClick: (actions: ReturnType<typeof useFlowActions>) => {
      return actions.createNodeTemplate({ x: 250, y: 100 });
    }
  },
  {
    icon: MessageSquare,
    label: "Message with buttons",
    bgColor: "bg-orange-100",
    textColor: "text-orange-600",
    onClick: (actions: ReturnType<typeof useFlowActions>) => {
      const position = { x: 250, y: 100 };
      const data: NodeData = {
        label: "Quick Replies Message",
        type: "quick_replies",
        settings: {
          header: "",
          body: "",
          footer: "",
          button1: "",
          button2: "",
          button3: ""
        }
      };
      return actions.createNodeBase('quick_replies', position, data);
    }
  },
  {
    icon: List,
    label: "Send list message",
    bgColor: "bg-green-100",
    textColor: "text-green-600",
    onClick: (actions: ReturnType<typeof useFlowActions>) => {
      const position = { x: 250, y: 100 };
      const data: NodeData = {
        label: "List Message",
        type: "list_message",
        settings: {
          header: "",
          body: "",
          footer: "",
          buttonText: "Choose an option",
          sections: [
            {
              id: "section1",
              title: "Options",
              rows: [
                {
                  id: "row1",
                  title: "Option 1",
                  description: "Description for option 1"
                }
              ]
            }
          ]
        }
      };
      return actions.createNodeBase('list_message', position, data);
    }
  }
];

export const MessagingSection = ({ searchQuery }: MessagingSectionProps) => {
  const actions = useFlowActions();

  const filteredActions = messagingActions.filter(action => 
    action.label.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (filteredActions.length === 0) return null;

  return (
    <div className="grid gap-2">
      {filteredActions.map((action, index) => (
        <Button
          key={index}
          variant="ghost"
          className="w-full justify-start text-gray-700 hover:text-gray-900 focus:ring-0 focus-visible:ring-0 focus:outline-none"
          onClick={() => action.onClick(actions)}
        >
          <div className={`${action.bgColor} p-2 rounded-lg mr-3`}>
            <action.icon className={`h-5 w-5 ${action.textColor}`} />
          </div>
          {action.label}
        </Button>
      ))}
    </div>
  );
};
