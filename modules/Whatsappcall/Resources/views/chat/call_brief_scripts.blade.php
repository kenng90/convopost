<script>
"use strict";
(function registerCallBriefHelpers() {
    function attach() {
        if (!chatList || typeof chatList.$mount !== 'function' || chatList._callBriefAttached) {
            return !!chatList && chatList._callBriefAttached;
        }
        chatList._callBriefAttached = true;

        chatList.callBriefPayload = function (message) {
            if (!message) return {};
            if (message.call_brief_payload && typeof message.call_brief_payload === 'object') {
                return message.call_brief_payload;
            }
            if (typeof message.call_brief_payload === 'string') {
                try { return JSON.parse(message.call_brief_payload); } catch (e) { return {}; }
            }
            return {};
        };

        chatList.callBriefTitle = function (message) {
            var p = chatList.callBriefPayload(message);
            var parts = [@json(__('AI call'))];
            if (p.duration_seconds) {
                var m = Math.floor(p.duration_seconds / 60);
                var s = p.duration_seconds % 60;
                parts.push(m > 0 ? (m + 'm ' + String(s).padStart(2, '0') + 's') : (s + 's'));
            }
            if (p.handoff_requested) {
                parts.push(@json(__('handoff requested')));
            }
            return parts.join(' · ');
        };

        chatList.callBriefBullets = function (message) {
            return chatList.callBriefPayload(message).summary_bullets || [];
        };

        chatList.callBriefFields = function (message) {
            return chatList.callBriefPayload(message).fields || [];
        };

        chatList.callBriefFieldIcon = function (status) {
            switch (status) {
                case 'confirmed': return '✓';
                case 'corrected': return '↻';
                case 'missing': return '✗';
                default: return '~';
            }
        };

        return true;
    }

    if (!attach()) {
        window.addEventListener('load', function () {
            if (!attach()) {
                var attempts = 0;
                var timer = setInterval(function () {
                    if (attach() || ++attempts > 50) {
                        clearInterval(timer);
                    }
                }, 100);
            }
        });
    }
})();
</script>
