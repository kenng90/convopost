import { Archive, ArchiveRestore, Edit, MessageSquare, Tag, UserMinus, UserPlus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useFlowActions } from "@/hooks/useFlowActions";

interface ActionsSectionProps {
  searchQuery: string;
}

export const ActionsSection = ({ searchQuery }: ActionsSectionProps) => {
  const { createNodeBase } = useFlowActions();

  const actions = [
    {
      label: "Assign conversation to agent",
      icon: UserPlus,
      onClick: () => createNodeBase("assign_agent", { x: 100, y: 100 }, {
        settings: { actionType: "assign_agent" }
      })
    },
    {
      label: "Assign conversation to team",
      icon: UserPlus,
      onClick: () => createNodeBase("assign_team", { x: 100, y: 100 }, {
        settings: { actionType: "assign_team" }
      })
    },
    {
      label: "Unassign conversation from agent",
      icon: UserMinus,
      onClick: () => createNodeBase("unassign_agent", { x: 100, y: 100 }, {
        settings: { actionType: "unassign_agent" }
      })
    },
    {
      label: "Unassign conversation from team",
      icon: UserMinus,
      onClick: () => createNodeBase("unassign_team", { x: 100, y: 100 }, {
        settings: { actionType: "unassign_team" }
      })
    },
    {
      label: "Add/remove internal note",
      icon: MessageSquare,
      onClick: () => createNodeBase("internal_note", { x: 100, y: 100 }, {
        settings: { actionType: "internal_note" }
      })
    },
    {
      label: "Add/remove contact label",
      icon: Tag,
      onClick: () => createNodeBase("contact_label", { x: 100, y: 100 }, {
        settings: { actionType: "contact_label" }
      })
    },
    {
      label: "Add/remove tag",
      icon: Tag,
      onClick: () => createNodeBase("tag", { x: 100, y: 100 }, {
        settings: { actionType: "tag" }
      })
    },
    {
      label: "Add to contact list",
      icon: UserPlus,
      onClick: () => createNodeBase("add_contact", { x: 100, y: 100 }, {
        settings: { actionType: "add_contact" }
      })
    },
    {
      label: "Remove from contact list",
      icon: UserMinus,
      onClick: () => createNodeBase("remove_contact", { x: 100, y: 100 }, {
        settings: { actionType: "remove_contact" }
      })
    },
    {
      label: "Archive conversation",
      icon: Archive,
      onClick: () => createNodeBase("archive", { x: 100, y: 100 }, {
        settings: { actionType: "archive" }
      })
    },
    {
      label: "Unarchive conversation",
      icon: ArchiveRestore,
      onClick: () => createNodeBase("unarchive", { x: 100, y: 100 }, {
        settings: { actionType: "unarchive" }
      })
    },
    {
      label: "Update contact field(s)",
      icon: Edit,
      onClick: () => createNodeBase("update_contact", { x: 100, y: 100 }, {
        settings: { actionType: "update_contact" }
      })
    }
  ];

  const filteredActions = actions.filter(action =>
    action.label.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (filteredActions.length === 0) return null;

  return (
    <div className="space-y-4">
      <h3 className="font-medium">Actions</h3>
      <div className="grid gap-2">
        {filteredActions.map((action, index) => (
          <Button
            key={index}
            variant="outline"
            className="w-full justify-start"
            onClick={action.onClick}
          >
            <action.icon className="mr-2 h-4 w-4" />
            {action.label}
          </Button>
        ))}
      </div>
    </div>
  );
};