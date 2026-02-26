
import { Key } from 'lucide-react';

const KeywordHeader = () => {
  return (
    <div className="flex items-center gap-2 border-b border-gray-100 px-4 py-3 bg-gray-50 rounded-t-lg">
      <Key className="h-5 w-5 text-yellow-500 flex-shrink-0" />
      <div className="font-medium text-gray-800">Keyword Trigger</div>
    </div>
  );
};

export default KeywordHeader;
