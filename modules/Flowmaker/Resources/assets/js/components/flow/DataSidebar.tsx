import { Sheet, SheetContent, SheetClose } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { X, Bot, Globe, Upload, FileText, Plus, Trash2 } from "lucide-react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import React, { useState, useEffect, useRef } from "react";

interface DataSidebarProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

interface FAQ {
  id: string;
  question: string;
  answer: string;
}

interface TrainedWebsite {
  id: string;
  url: string;
}

interface TrainedFile {
  id: string;
  name: string;
  type: string;
}

const DataSidebar = ({ open, onOpenChange }: DataSidebarProps) => {
  const [websiteUrl, setWebsiteUrl] = useState("");
  const [isProcessing, setIsProcessing] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [isProcessingFAQ, setIsProcessingFAQ] = useState(false);
  const [isProcessingFile, setIsProcessingFile] = useState(false);
  
  // State for FAQ inputs
  const [currentQuestion, setCurrentQuestion] = useState("");
  const [currentAnswer, setCurrentAnswer] = useState("");
  
  // File upload ref
  const fileInputRef = useRef<HTMLInputElement>(null);
  
  // Initialize with empty arrays
  const [faqs, setFaqs] = useState<FAQ[]>([]);
  const [trainedWebsites, setTrainedWebsites] = useState<TrainedWebsite[]>([]);
  const [trainedFiles, setTrainedFiles] = useState<TrainedFile[]>([]);
  
  // Load data from backend API when component mounts
  useEffect(() => {
    const loadTrainingData = async () => {
      if (!open) return; // Only load when sidebar is open
      
      setIsLoading(true);
      
      try {
        // Get flow ID from the current context
        const flowId = window.location.pathname.split('/').pop();
        
        const response = await fetch(`/ai/training-data/${flowId}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          }
        });

        if (response.ok) {
          const data = await response.json();
          
          if (data.faqs) {
            setFaqs(data.faqs);
          }
          
          if (data.trainedWebsites) {
            setTrainedWebsites(data.trainedWebsites);
          }
          
          if (data.trainedFiles) {
            setTrainedFiles(data.trainedFiles);
          }
        } else {
          console.error('Failed to load training data');
        }
      } catch (error) {
        console.error('Error loading training data:', error);
      } finally {
        setIsLoading(false);
      }
    };

    loadTrainingData();
  }, [open]); // Re-load when sidebar opens

  const handleAddWebsite = async () => {
    if (!websiteUrl) return;
    
    setIsProcessing(true);
    
    try {
      // Get flow ID from the current context (you may need to adjust this based on your app structure)
      const flowId = window.location.pathname.split('/').pop(); // Extract flow ID from URL
      
      const response = await fetch('/ai/process-website', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          url: websiteUrl,
          flow_id: flowId
        })
      });

      const data = await response.json();

      if (response.ok && data.success) {
        // Add website to trained websites
        const newTrainedWebsite = {
          id: data.document.id,
          url: websiteUrl
        };
        setTrainedWebsites([...trainedWebsites, newTrainedWebsite]);
        setWebsiteUrl("");
        
        // You could show a success message here
        console.log('Website processed successfully:', data.message);
      } else {
        // Handle error
        console.error('Error processing website:', data.error || 'Unknown error');
        alert(data.error || 'Failed to process website');
      }
    } catch (error) {
      console.error('Network error:', error);
      alert('Failed to process website. Please check your internet connection.');
    } finally {
      setIsProcessing(false);
    }
  };
  
  const handleAddFAQ = async () => {
    console.log('🔄 1. Starting FAQ processing...', {
      question: currentQuestion,
      answer: currentAnswer,
      flowId: window.location.pathname.split('/').pop()
    });
    
    if (!currentQuestion.trim() || !currentAnswer.trim()) return;
    
    console.log('🔄 Starting FAQ processing...', {
      question: currentQuestion,
      answer: currentAnswer,
      flowId: window.location.pathname.split('/').pop()
    });
    
    setIsProcessingFAQ(true);
    
    try {
      const flowId = window.location.pathname.split('/').pop();
      
      console.log('📡 Making API call to /ai/process-faq...', {
        url: '/ai/process-faq',
        method: 'POST',
        flowId: flowId
      });
      
      const response = await fetch('/ai/process-faq', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          question: currentQuestion,
          answer: currentAnswer,
          flow_id: flowId
        })
      });

      console.log('📨 API Response received:', {
        status: response.status,
        statusText: response.statusText,
        ok: response.ok
      });

      const data = await response.json();
      
      console.log('📋 Response data:', data);

      if (response.ok && data.success) {
        // Add FAQ to the list
        const newFAQ: FAQ = {
          id: data.document.id,
          question: currentQuestion,
          answer: currentAnswer
        };
        
        setFaqs([...faqs, newFAQ]);
        setCurrentQuestion("");
        setCurrentAnswer("");
        
        console.log('✅ FAQ processed successfully:', data.message);
        console.log('📊 New FAQ added to state:', newFAQ);
        
        // Show success message
        //alert('FAQ added and embedded successfully!');
      } else {
        console.error('❌ Error processing FAQ:', data.error || 'Unknown error');
        console.error('Full error response:', data);
        alert(data.error || 'Failed to process FAQ');
      }
    } catch (error) {
      console.error('🚨 Network/fetch error:', error);
      console.error('Error details:', {
        message: error.message,
        stack: error.stack
      });
      alert('Failed to process FAQ. Please check your internet connection and try again.');
    } finally {
      console.log('🏁 FAQ processing completed');
      setIsProcessingFAQ(false);
    }
  };
  
  const handleDeleteFAQ = async (id: string) => {
    if (!confirm('Are you sure you want to delete this FAQ?')) return;
    
    try {
      const response = await fetch(`/ai/document/${id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        }
      });

      if (response.ok) {
        setFaqs(faqs.filter(faq => faq.id !== id));
        console.log('FAQ deleted successfully');
      } else {
        const data = await response.json();
        console.error('Error deleting FAQ:', data.error || 'Unknown error');
        alert(data.error || 'Failed to delete FAQ');
      }
    } catch (error) {
      console.error('Network error:', error);
      alert('Failed to delete FAQ. Please check your internet connection.');
    }
  };

  const handleDeleteTrainedWebsite = async (id: string) => {
    if (!confirm('Are you sure you want to delete this website?')) return;
    
    try {
      const response = await fetch(`/ai/document/${id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        }
      });

      if (response.ok) {
        setTrainedWebsites(trainedWebsites.filter(website => website.id !== id));
        console.log('Website deleted successfully');
      } else {
        const data = await response.json();
        console.error('Error deleting website:', data.error || 'Unknown error');
        alert(data.error || 'Failed to delete website');
      }
    } catch (error) {
      console.error('Network error:', error);
      alert('Failed to delete website. Please check your internet connection.');
    }
  };

  const handleDeleteTrainedFile = async (id: string) => {
    if (!confirm('Are you sure you want to delete this file?')) return;
    
    try {
      const response = await fetch(`/ai/document/${id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        }
      });

      if (response.ok) {
        setTrainedFiles(trainedFiles.filter(file => file.id !== id));
        console.log('File deleted successfully');
      } else {
        const data = await response.json();
        console.error('Error deleting file:', data.error || 'Unknown error');
        alert(data.error || 'Failed to delete file');
      }
    } catch (error) {
      console.error('Network error:', error);
      alert('Failed to delete file. Please check your internet connection.');
    }
  };

  const handleFileUpload = () => {
    if (fileInputRef.current) {
      fileInputRef.current.click();
    }
  };

  const handleFileSelected = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const files = event.target.files;
    if (!files || files.length === 0) return;

    const file = files[0];
    const allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'text/plain'];
    const maxSize = 20 * 1024 * 1024; // 20MB

    // Validate file type
    if (!allowedTypes.includes(file.type)) {
      alert('Please select a PDF, Word document, or TXT file.');
      return;
    }

    // Validate file size
    if (file.size > maxSize) {
      alert('File size must be less than 20MB.');
      return;
    }

    console.log('📁 Starting file upload and processing...', {
      name: file.name,
      type: file.type,
      size: file.size
    });

    setIsProcessingFile(true);

    try {
      // First, upload the file using the existing upload endpoint
      const formData = new FormData();
      formData.append('file', file);
      formData.append('type', 'document');

      console.log('📤 Uploading file...');

      const uploadResponse = await fetch('/flowmakermedia', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: formData
      });

      const uploadData = await uploadResponse.json();

      if (!uploadResponse.ok || uploadData.status !== 'success') {
        throw new Error(uploadData.error || 'Failed to upload file');
      }

      console.log('✅ File uploaded successfully:', uploadData.url);

      // Now process the file for embeddings
      const flowId = window.location.pathname.split('/').pop();
      const fileExtension = file.name.split('.').pop()?.toLowerCase();

      console.log('🧠 Processing file for embeddings...', {
        fileUrl: uploadData.url,
        fileName: file.name,
        fileType: fileExtension,
        flowId: flowId
      });

      const processResponse = await fetch('/ai/process-file', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          file_url: uploadData.url,
          file_name: file.name,
          file_type: fileExtension,
          flow_id: flowId
        })
      });

      const processData = await processResponse.json();

      console.log('📨 File processing response:', processData);

      if (processResponse.ok && processData.success) {
        // Add file to trained files
        const newTrainedFile = {
          id: processData.document.id,
          name: file.name,
          type: fileExtension?.toUpperCase() || 'FILE'
        };
        
        setTrainedFiles([...trainedFiles, newTrainedFile]);
        
        console.log('✅ File processed and embedded successfully!');
        console.log('📊 Chunks created:', processData.chunk_count);
        
        alert(`File processed successfully! Created ${processData.chunk_count} text chunks for AI training.`);
      } else {
        console.error('❌ Error processing file:', processData.error || 'Unknown error');
        alert(processData.error || 'Failed to process file for AI training');
      }
    } catch (error) {
      console.error('🚨 File upload/processing error:', error);
      alert('Failed to upload or process file. Please try again.');
    } finally {
      console.log('🏁 File processing completed');
      setIsProcessingFile(false);
      
      // Reset file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-[400px] sm:w-[540px] p-0 border-l">
        <div className="flex flex-col h-full">
          <div className="flex items-center justify-between border-b p-4">
            <div className="flex items-center gap-2">
              <Bot className="h-5 w-5 text-purple-600" />
              <h2 className="text-lg font-medium">AI Bot Training</h2>
            </div>
            <SheetClose asChild>
              <Button 
                variant="ghost" 
                size="icon"
                onClick={() => onOpenChange(false)}
              >
                <X className="h-4 w-4" />
              </Button>
            </SheetClose>
          </div>
          <div className="flex-1 overflow-auto p-4">
            {isLoading ? (
              <div className="flex items-center justify-center py-8">
                <div className="text-sm text-muted-foreground">Loading training data...</div>
              </div>
            ) : (
              <Tabs defaultValue="training" className="w-full">
                <TabsList className="grid grid-cols-2 mb-4">
                  <TabsTrigger value="training">Training</TabsTrigger>
                  <TabsTrigger value="data">Data</TabsTrigger>
                </TabsList>
                <TabsContent value="training" className="space-y-4">                
                  <div className="rounded-lg border p-4">
                    <h3 className="font-medium mb-2">Website Training</h3>
                    <p className="text-sm text-muted-foreground mb-3">
                      Train your bot using content from websites.
                    </p>
                    <div className="flex gap-2">
                      <Input 
                        placeholder="https://example.com" 
                        value={websiteUrl}
                        onChange={(e) => setWebsiteUrl(e.target.value)}
                        className="text-sm"
                      />
                      <Button 
                        size="sm" 
                        onClick={handleAddWebsite}
                        disabled={!websiteUrl || isProcessing}
                      >
                        <Globe className="h-4 w-4 mr-2" />
                        {isProcessing ? "Processing..." : "Add"}
                      </Button>
                    </div>
                  </div>

                  <div className="rounded-lg border p-4">
                    <h3 className="font-medium mb-2">Upload Files</h3>
                    <p className="text-sm text-muted-foreground mb-3">
                      Upload PDF, Word documents, or TXT files to train your bot.
                    </p>
                    <input
                      ref={fileInputRef}
                      type="file"
                      accept=".pdf,.docx,.doc,.txt"
                      onChange={handleFileSelected}
                      style={{ display: 'none' }}
                    />
                    <Button 
                      variant="outline" 
                      size="sm" 
                      className="w-full" 
                      onClick={handleFileUpload}
                      disabled={isProcessingFile}
                    >
                      <Upload className="h-4 w-4 mr-2" />
                      {isProcessingFile ? "Processing..." : "Upload Files"}
                    </Button>
                  </div>
                  
                  <div className="rounded-lg border p-4">
                    <h3 className="font-medium mb-2">FAQ Training</h3>
                    <p className="text-sm text-muted-foreground mb-3">
                      Add frequently asked questions to train your bot.
                    </p>
                    
                    <div className="space-y-2 mt-2">
                      <div>
                        <Label htmlFor="question" className="text-xs">Question</Label>
                        <Input 
                          id="question"
                          placeholder="Enter a question" 
                          value={currentQuestion}
                          onChange={(e) => setCurrentQuestion(e.target.value)}
                          className="text-sm h-8"
                        />
                      </div>
                      <div>
                        <Label htmlFor="answer" className="text-xs">Answer</Label>
                        <Input 
                          id="answer"
                          placeholder="Enter the answer" 
                          value={currentAnswer}
                          onChange={(e) => setCurrentAnswer(e.target.value)}
                          className="text-sm h-8"
                        />
                      </div>
                      <Button 
                        size="sm" 
                        variant="outline" 
                        className="w-full" 
                        onClick={handleAddFAQ}
                        disabled={!currentQuestion.trim() || !currentAnswer.trim() || isProcessingFAQ}
                      >
                        <FileText className="h-4 w-4 mr-2" />
                        {isProcessingFAQ ? "Processing..." : "Add FAQs"}
                      </Button>
                    </div>
                  </div>
                </TabsContent>
                <TabsContent value="data" className="space-y-4">
                  {/* Websites section */}
                  <div className="rounded-lg border p-4">
                    <h3 className="font-medium mb-2">Trained Websites</h3>
                    {trainedWebsites.length === 0 ? (
                      <p className="text-sm text-muted-foreground">No websites have been added for training.</p>
                    ) : (
                      <div className="space-y-2">
                        {trainedWebsites.map(website => (
                          <div key={website.id} className="bg-muted rounded-md p-3 flex justify-between items-center">
                            <div className="flex items-center">
                              <Globe className="h-4 w-4 text-blue-500 mr-2" />
                              <span className="text-sm" title={website.url}>
                                {website.url.length > 30 ? `${website.url.substring(0, 30)}...` : website.url}
                              </span>
                            </div>
                            <Button 
                              variant="ghost" 
                              size="icon" 
                              className="h-7 w-7" 
                              onClick={() => handleDeleteTrainedWebsite(website.id)}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                  
                  {/* Files section */}
                  <div className="rounded-lg border p-4">
                    <h3 className="font-medium mb-2">Trained Files</h3>
                    {trainedFiles.length === 0 ? (
                      <p className="text-sm text-muted-foreground">No files have been uploaded for training.</p>
                    ) : (
                      <div className="space-y-2">
                        {trainedFiles.map(file => (
                          <div key={file.id} className="bg-muted rounded-md p-3 flex justify-between items-center">
                            <div className="flex items-center">
                              <FileText className="h-4 w-4 text-amber-500 mr-2" />
                              <span className="text-sm">{file.name}</span>
                            </div>
                            <Button 
                              variant="ghost" 
                              size="icon" 
                              className="h-7 w-7" 
                              onClick={() => handleDeleteTrainedFile(file.id)}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                  
                  {/* FAQs section */}
                  <div className="rounded-lg border p-4">
                    <h3 className="font-medium mb-2">Trained FAQs</h3>
                    {faqs.length === 0 ? (
                      <p className="text-sm text-muted-foreground">No FAQs have been added for training.</p>
                    ) : (
                      <div className="space-y-2">
                        {faqs.map(faq => (
                          <div key={faq.id} className="bg-muted rounded-md p-3 relative pr-8">
                            <div className="font-medium text-sm">{faq.question}</div>
                            <div className="text-xs text-muted-foreground mt-1">{faq.answer}</div>
                            <Button 
                              variant="ghost" 
                              size="icon" 
                              className="absolute top-2 right-2 h-6 w-6" 
                              onClick={() => handleDeleteFAQ(faq.id)}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </TabsContent>
              </Tabs>
            )}
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
};

export default DataSidebar;
