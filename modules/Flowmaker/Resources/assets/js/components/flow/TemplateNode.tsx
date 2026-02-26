import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { Handle, Position } from '@xyflow/react';
import { MessageSquare, FileText, Image, Video, File, Upload, Check, Phone, Trash2 } from 'lucide-react';
import { 
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger 
} from "@/components/ui/context-menu";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { templates } from '@/data/templateData';
import { NodeData } from '@/types/flow';
import { VariableInput } from '@/components/common/VariableInput';
import { useFlowActions } from "@/hooks/useFlowActions";
import { Button } from "@/components/ui/button";

interface TemplateNodeProps {
  data: NodeData;
  id: string;
}

interface ButtonComponent {
  type: 'QUICK_REPLY' | 'PHONE_NUMBER' | 'URL' | 'COPY_CODE';
  text: string;
  phone_number?: string;
  url?: string;
  example?: string[];
}

interface Component {
  type: 'HEADER' | 'BODY' | 'FOOTER' | 'BUTTONS';
  format?: 'TEXT' | 'IMAGE' | 'VIDEO' | 'DOCUMENT';
  text?: string;
  example?: {
    header_text?: string[];
    body_text?: string[][];
    header_handle?: string[];
  };
  buttons?: ButtonComponent[];
}

interface VariableInputProps {
  value: string;
  onChange: (value: string) => void;
  placeholder: string;
  id: string;
}

const TemplateNode = ({ data, id }: TemplateNodeProps) => {
  const { deleteNode } = useFlowActions();
  
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>(data.settings?.selectedTemplateId || "");
  const [parameters, setParameters] = useState<Record<string, string>>(data.settings?.parameters || {});
  const [fileUrl, setFileUrl] = useState<string>(data.settings?.fileUrl || "");
  const [videoUrl, setVideoUrl] = useState<string>(data.settings?.videoUrl || "");
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  useEffect(() => {
    if (data) {
      data.settings = {
        selectedTemplateId,
        parameters,
        fileUrl,
        videoUrl
      };
    }
  }, [selectedTemplateId, parameters, fileUrl, videoUrl, data]);

  const selectedTemplate = useMemo(() => {
    console.log("Selected template ID:", selectedTemplateId);
    const template = templates.find(t => t.id === selectedTemplateId);
    console.log("Found template:", template);
    return template;
  }, [selectedTemplateId]);

  const handleParameterChange = (key: string, value: string) => {
    const newParameters = {
      ...parameters,
      [key]: value
    };
    setParameters(newParameters);
    if (data) {
      data.settings = {
        ...data.settings,
        parameters: newParameters
      };
    }
  };

  const extractVariables = (text?: string, type: 'HEADER' | 'BODY' = 'BODY'): string[] => {
    if (!text) return [];
    const matches = text.match(/{{(\d+)}}/g) || [];
    const uniqueMatches = [...new Set(matches.map(match => {
      const num = match.replace(/[{}]/g, '');
      return `${type}_${num}`;
    }))];
    return uniqueMatches;
  };

  const replaceVariables = (text?: string, type: 'HEADER' | 'BODY' = 'BODY'): string => {
    if (!text) return '';
    return text.replace(/{{(\d+)}}/g, (match, number) => {
      const key = `${type}_${number}`;
      return parameters[key] || match;
    });
  };

  const getHeaderIcon = (format?: string) => {
    switch (format) {
      case 'IMAGE': return <Image className="h-4 w-4 text-indigo-600" />;
      case 'VIDEO': return <Video className="h-4 w-4 text-indigo-600" />;
      case 'DOCUMENT': return <File className="h-4 w-4 text-indigo-600" />;
      default: return <MessageSquare className="h-4 w-4 text-indigo-600" />;
    }
  };

  const handleFileUpload = useCallback((event: React.ChangeEvent<HTMLInputElement>, type: 'image' | 'video' | 'document') => {
    const file = event.target.files?.[0];
    if (!file) return;
    
    // Validate file type
    if (type === 'image' && !file.type.startsWith('image/')) {
      setUploadError('Please upload an image file');
      return;
    }
    
    if (type === 'video' && !file.type.startsWith('video/')) {
      setUploadError('Please upload a video file');
      return;
    }

    if (type === 'document' && file.type !== 'application/pdf') {
      setUploadError('Please upload a PDF file');
      return;
    }

    setIsUploading(true);
    setUploadError(null);
    
    // Create FormData and append file
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);

    // Upload to server
    fetch('/flowmakermedia', {
      method: 'POST',
      body: formData,
    })
      .then(response => response.json())
      .then(responseData => {
        if (responseData.error) {
          throw new Error(responseData.error);
        }
        
        if (type === 'image') {
          setFileUrl(responseData.url);
          if (data) {
            data.settings = {
              ...data.settings,
              fileUrl: responseData.url
            };
          }
        } else if (type === 'video') {
          setVideoUrl(responseData.url);
          if (data) {
            data.settings = {
              ...data.settings,
              videoUrl: responseData.url
            };
          }
        } else if (type === 'document') {
          setFileUrl(responseData.url);
          if (data) {
            data.settings = {
              ...data.settings,
              fileUrl: responseData.url
            };
          }
        }
        
        setIsUploading(false);
      })
      .catch(error => {
        console.error('Error uploading file:', error);
        setUploadError(error.message || 'Failed to upload file');
        setIsUploading(false);
      });
  }, [data]);

  const renderHeader = (component: Component) => {
    if (!component.format) return null;
    
    switch (component.format) {
      case 'TEXT':
        return (
          <div className="font-medium mb-2 text-gray-700">
            {replaceVariables(component.text, 'HEADER')}
          </div>
        );
      case 'IMAGE':
        return component.example?.header_handle?.[0] ? (
          <div className="mb-2">
            {fileUrl ? (
              <div className="relative group">
                <img 
                  src={fileUrl}
                  alt="Header"
                  className="w-full h-32 object-cover rounded-md"
                />
                <div className="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                      const input = document.getElementById(`image-upload-${id}`);
                      input?.click();
                    }}
                  >
                    Change Image
                  </Button>
                </div>
              </div>
            ) : (
              <div 
                className="border-2 border-dashed border-gray-200 rounded-md p-4 text-center cursor-pointer hover:border-gray-300 transition-colors"
                onClick={() => {
                  const input = document.getElementById(`image-upload-${id}`);
                  input?.click();
                }}
              >
                <Upload className="w-6 h-6 mx-auto mb-2 text-gray-400" />
                <div className="text-sm text-gray-500">
                  Click to upload an image
                </div>
              </div>
            )}
            <input
              type="file"
              id={`image-upload-${id}`}
              className="hidden"
              accept="image/*"
              onChange={(e) => handleFileUpload(e, 'image')}
              disabled={isUploading}
            />
            {isUploading && (
              <div className="text-sm text-center text-blue-600">
                Uploading image...
              </div>
            )}
            {uploadError && (
              <div className="text-sm text-center text-red-600">
                {uploadError}
              </div>
            )}
          </div>
        ) : null;
      case 'VIDEO':
        return component.example?.header_handle?.[0] ? (
          <div className="mb-2">
            {videoUrl ? (
              <div className="relative group">
                <video 
                  src={videoUrl}
                  className="w-full h-32 object-cover rounded-md"
                  controls
                />
                <div className="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                      const input = document.getElementById(`video-upload-${id}`);
                      input?.click();
                    }}
                  >
                    Change Video
                  </Button>
                </div>
              </div>
            ) : (
              <div 
                className="border-2 border-dashed border-gray-200 rounded-md p-4 text-center cursor-pointer hover:border-gray-300 transition-colors"
                onClick={() => {
                  const input = document.getElementById(`video-upload-${id}`);
                  input?.click();
                }}
              >
                <Upload className="w-6 h-6 mx-auto mb-2 text-gray-400" />
                <div className="text-sm text-gray-500">
                  Click to upload a video
                </div>
              </div>
            )}
            <input
              type="file"
              id={`video-upload-${id}`}
              className="hidden"
              accept="video/*"
              onChange={(e) => handleFileUpload(e, 'video')}
              disabled={isUploading}
            />
            {isUploading && (
              <div className="text-sm text-center text-blue-600">
                Uploading video...
              </div>
            )}
            {uploadError && (
              <div className="text-sm text-center text-red-600">
                {uploadError}
              </div>
            )}
          </div>
        ) : null;
      case 'DOCUMENT':
        return (
          <div className="mb-2">
            <div className="bg-gray-100 p-3 rounded-md flex items-center gap-2">
              <FileText className="h-5 w-5 text-indigo-600" />
              <div>
                <div className="font-medium">Document</div>
                <div className="text-xs text-gray-500">
                  {fileUrl ? "PDF Document Uploaded" : "No document selected"}
                </div>
              </div>
            </div>
            <div 
              className="mt-2 border-2 border-dashed border-gray-200 rounded-md p-4 text-center cursor-pointer hover:border-gray-300 transition-colors"
              onClick={() => {
                const input = document.getElementById(`pdf-upload-${id}`);
                input?.click();
              }}
            >
              <Upload className="w-6 h-6 mx-auto mb-2 text-gray-400" />
              <div className="text-sm text-gray-500">
                Click to upload a PDF file
              </div>
            </div>
            <input
              type="file"
              id={`pdf-upload-${id}`}
              className="hidden"
              accept="application/pdf"
              onChange={(e) => handleFileUpload(e, 'document')}
              disabled={isUploading}
            />
            {isUploading && (
              <div className="text-sm text-center text-blue-600">
                Uploading document...
              </div>
            )}
            {uploadError && (
              <div className="text-sm text-center text-red-600">
                {uploadError}
              </div>
            )}
          </div>
        );
      default:
        return null;
    }
  };

  const renderBody = (component: Component) => {
    return (
      <div className="text-sm text-gray-600 mb-2 whitespace-pre-line">
        {replaceVariables(component.text, 'BODY')}
      </div>
    );
  };

  const renderFooter = (component: Component) => {
    return (
      <div className="text-xs text-gray-500 mt-1">
        {replaceVariables(component.text)}
      </div>
    );
  };

  const renderButtons = (component: Component) => {
    console.log("Rendering buttons for component:", component);
    
    const buttonsList = component.buttons;
    
    if (!buttonsList?.length) {
      console.log("No buttons found in component:", component);
      return null;
    }
    
    console.log(`Found ${buttonsList.length} buttons to render:`, buttonsList);
    
    const hasQuickReply = buttonsList.some(button => button.type === 'QUICK_REPLY');
    
    return (
      <div className="space-y-2 mt-3">
        {buttonsList.map((button: ButtonComponent, index: number) => {
          console.log(`Rendering button ${index}:`, button);
          const buttonText = replaceVariables(button.text);
          
          switch (button.type) {
            case "QUICK_REPLY":
              return (
                <div key={index} className="relative flex items-center">
                  <button
                    className="w-full text-sm bg-gray-100 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-200 transition-colors flex items-center gap-2"
                  >
                    <Check className="h-4 w-4" />
                    {buttonText}
                  </button>
                  <Handle
                    type="source"
                    position={Position.Right}
                    id={`quick-reply-${index}`}
                    className="!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white"
                    style={{ 
                      right: '-20px',
                      top: '50%',
                      transform: 'translateY(-50%)',
                      zIndex: 50
                    }}
                  />
                </div>
              );
            case "PHONE_NUMBER":
              return (
                <div key={index} className="relative flex items-center">
                  <button
                    className="w-full text-sm bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 transition-colors flex items-center gap-2"
                    title={button.phone_number}
                  >
                    <Phone className="h-4 w-4" />
                    {buttonText}
                  </button>
                </div>
              );
            case "URL":
              return (
                <div key={index} className="relative flex items-center">
                  <button
                    className="w-full text-sm bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 transition-colors flex items-center gap-2"
                    title={button.url}
                  >
                    <FileText className="h-4 w-4" />
                    {buttonText}
                  </button>
                </div>
              );
            case "COPY_CODE":
              return (
                <div key={index} className="relative flex items-center">
                  <button
                    className="w-full text-sm bg-purple-500 text-white py-2 px-4 rounded-md hover:bg-purple-600 transition-colors flex items-center gap-2"
                  >
                    <FileText className="h-4 w-4" />
                    {buttonText}
                  </button>
                </div>
              );
            default:
              console.log(`Unknown button type: ${button.type}`);
              return null;
          }
        })}
        {!hasQuickReply && (
          <div className="mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2">
            <span className="text-xs text-gray-500">Always exit</span>
            <Handle
              type="source"
              position={Position.Right}
              id="always"
              className="!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white"
              style={{ 
                position: 'relative',
                right: '-8px',
                transform: 'translateY(0)',
                display: 'inline-block'
              }}
            />
          </div>
        )}
      </div>
    );
  };

  const templateVariables = useMemo(() => {
    if (!selectedTemplate) return [];
    
    const allVariables: string[] = [];
    
    selectedTemplate.components.forEach(component => {
      if (component.text) {
        const vars = extractVariables(component.text, component.type);
        vars.forEach(v => {
          if (!allVariables.includes(v)) {
            allVariables.push(v);
          }
        });
      }
    });
    
    return allVariables.sort();
  }, [selectedTemplate]);

  const hasButtons = useMemo(() => {
    if (!selectedTemplate) {
      console.log("No template selected, hasButtons = false");
      return false;
    }
    
    console.log("Checking for buttons in template components:", selectedTemplate.components);
    
    const result = selectedTemplate.components.some(component => {
      if (component.type === 'BUTTONS') {
        console.log("Found BUTTONS component:", component);
        if (component.buttons && Array.isArray(component.buttons)) {
          const hasQuickReply = component.buttons.some(button => button.type === 'QUICK_REPLY');
          console.log("Has quick reply buttons:", hasQuickReply);
          return hasQuickReply;
        }
        console.log("No buttons array found in BUTTONS component or it's not an array");
        return false;
      }
      return false;
    });
    
    console.log("hasButtons final result:", result);
    return result;
  }, [selectedTemplate]);

  const hasButtonsComponent = useMemo(() => {
    if (!selectedTemplate) return false;
    
    return selectedTemplate.components.some(component => 
      component.type === 'BUTTONS' && component.buttons && component.buttons.length > 0
    );
  }, [selectedTemplate]);

  return (
    <ContextMenu>
      <ContextMenuTrigger>
        <div className="bg-white rounded-lg shadow-lg w-[300px]">
          <Handle
            type="target"
            position={Position.Left}
            style={{ left: '-4px', background: '#555', zIndex: 50 }}
          />
          
          <div className="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100 px-4 pt-3 bg-gray-50">
            <MessageSquare className="h-4 w-4 text-indigo-600" />
            <div className="font-medium">Template Message</div>
          </div>

          <div className="p-4">
            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="template-select">WhatsApp Template</Label>
                <Select 
                  value={selectedTemplateId} 
                  onValueChange={(value) => {
                    setSelectedTemplateId(value);
                    if (data) {
                      data.settings = {
                        ...data.settings,
                        selectedTemplateId: value
                      };
                    }
                  }}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select a template" />
                  </SelectTrigger>
                  <SelectContent>
                    {templates.map((template) => (
                      <SelectItem key={template.id} value={template.id}>
                        {template.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {selectedTemplate && (
                <>
                  {templateVariables.length > 0 && (
                    <div className="space-y-2 px-4 pb-4">
                      <div className="space-y-2">
                        {templateVariables.map((variable) => (
                          <div key={variable} className="w-full">
                            <VariableInput
                              id={`var-${variable}`}
                              value={parameters[variable] || ""}
                              onChange={(value) => handleParameterChange(variable, value)}
                              placeholder={`Value for {{${variable}}}`}
                              className="w-full"
                            />
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  <div className="bg-[#DCF8C6] rounded-lg p-3 space-y-3">
                    {selectedTemplate.components.map((component, index) => (
                      <div key={index}>
                        {component.type === 'HEADER' && renderHeader(component)}
                        {component.type === 'BODY' && renderBody(component)}
                        {component.type === 'FOOTER' && renderFooter(component)}
                        {component.type === 'BUTTONS' && renderButtons(component)}
                      </div>
                    ))}

                    {!hasButtons && !hasButtonsComponent && (
                      <div className="mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2">
                        <span className="text-xs text-gray-500">Always exit</span>
                        <Handle
                          type="source"
                          position={Position.Right}
                          id="always"
                          className="!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white"
                          style={{ 
                            position: 'relative',
                            right: '-8px',
                            transform: 'translateY(0)',
                            display: 'inline-block'
                          }}
                        />
                      </div>
                    )}
                    {hasButtons && (
                      <div className="mt-3 pt-2 border-t border-green-200 flex items-center justify-end gap-2">
                        <div className="flex flex-col items-end">
                          <span className="text-xs text-gray-500">Else exit</span>
                          <span className="text-[10px] text-gray-400">Triggered when user reply is not known option</span>
                        </div>
                        <Handle
                          type="source"
                          position={Position.Right}
                          id="else"
                          className="!bg-gray-400 !w-3 !h-3 !min-w-[12px] !min-h-[12px] !border-2 !border-white"
                          style={{ 
                            position: 'relative',
                            right: '-8px',
                            transform: 'translateY(0)',
                            display: 'inline-block'
                          }}
                        />
                      </div>
                    )}
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      </ContextMenuTrigger>
      <ContextMenuContent>
        <ContextMenuItem
          className="text-red-600 focus:text-red-600 focus:bg-red-100"
          onClick={() => deleteNode(id)}
        >
          <Trash2 className="mr-2 h-4 w-4" />
          Delete
        </ContextMenuItem>
      </ContextMenuContent>
    </ContextMenu>
  );
};

export default TemplateNode;
