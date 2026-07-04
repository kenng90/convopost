import WebSocket from 'ws';
import { config } from './config.js';
import { logInfo, logWarn, logError, logDebug } from './logger.js';
import { bumpEvent } from './sessionDebug.js';

export class OpenAIRealtimeClient {
  constructor(options) {
    this.apiKey = options.apiKey;
    this.instructions = options.instructions;
    this.tools = options.tools ?? [];
    this.onToolCall = options.onToolCall;
    this.debug = options.debug ?? null;
    this.onAudioDelta = options.onAudioDelta;
    this.onUserTranscript = options.onUserTranscript;
    this.onAssistantTranscript = options.onAssistantTranscript;
    this.onError = options.onError;
    this.ws = null;
    this.sessionReady = false;
    this.closed = false;
    /** @type {string[]} */
    this.transcriptLines = [];
    this.currentAssistantText = '';
    this.connectStartedAt = Date.now();
    /** @type {Map<string, {name: string, arguments: string}>} */
    this.pendingFunctionCalls = new Map();
  }

  connect() {
    return new Promise((resolve, reject) => {
      if (!this.apiKey) {
        const err = new Error('OpenAI API key is not set for this call');
        logError('OpenAI connect aborted', { reason: err.message });
        reject(err);
        return;
      }

      const model = config.openaiRealtimeModel;
      const url = `wss://api.openai.com/v1/realtime?model=${encodeURIComponent(model)}`;

      logInfo('Connecting to OpenAI Realtime (GA)', { model, url_host: 'api.openai.com' });

      this.ws = new WebSocket(url, {
        headers: {
          Authorization: `Bearer ${this.apiKey}`,
        },
      });

      const fail = (err) => {
        if (!this.closed) {
          logError('OpenAI Realtime connection failed', {
            message: err?.message || String(err),
            elapsed_ms: Date.now() - this.connectStartedAt,
          });
          this.onError?.(err);
          reject(err);
        }
      };

      this.ws.on('error', (err) => {
        logError('OpenAI WebSocket error', { message: err.message, code: err.code });
        fail(err);
      });

      this.ws.on('open', () => {
        logInfo('OpenAI WebSocket open', {
          model,
          elapsed_ms: Date.now() - this.connectStartedAt,
        });
        if (this.debug) this.debug.openai_connected = true;
      });

      this.ws.on('message', (raw) => {
        let event;
        try {
          event = JSON.parse(raw.toString());
        } catch (e) {
          logWarn('OpenAI invalid JSON event', { error: e.message });
          return;
        }

        this.handleEvent(event, resolve, fail);
      });

      this.ws.on('close', (code, reason) => {
        this.closed = true;
        logInfo('OpenAI WebSocket closed', {
          code,
          reason: reason?.toString() || '',
        });
      });

      setTimeout(() => {
        if (!this.sessionReady && !this.closed) {
          fail(new Error('OpenAI session.updated timeout after 20s — check API key and model access'));
        }
      }, 20000);
    });
  }

  handleEvent(event, resolveReady, fail) {
    const type = event.type;
    if (this.debug) bumpEvent(this.debug, type);

    logDebug('OpenAI event', {
      type,
      ...(type === 'error' ? { error: event.error } : {}),
      ...(type === 'response.done' ? { status: event.response?.status } : {}),
    });

    if (type === 'error') {
      const msg = event.error?.message || JSON.stringify(event.error || event);
      logError('OpenAI error event', { message: msg, code: event.error?.code, type: event.error?.type });
      fail(new Error(msg));
      return;
    }

    if (type === 'session.created') {
      logInfo('OpenAI session.created — sending session.update');
      this.sendSessionUpdate();
      return;
    }

    if (type === 'session.updated') {
      if (!this.sessionReady) {
        this.sessionReady = true;
        if (this.debug) this.debug.openai_session_ready = true;
        logInfo('OpenAI session ready', { elapsed_ms: Date.now() - this.connectStartedAt });
        resolveReady();
      }
      return;
    }

    if (type === 'response.created') {
      logInfo('OpenAI response.created', { response_id: event.response?.id });
      return;
    }

    if (
      type === 'response.audio.delta' ||
      type === 'response.output_audio.delta'
    ) {
      const delta = event.delta;
      if (typeof delta === 'string' && delta.length > 0) {
        this.onAudioDelta?.(delta);
      } else {
        logDebug('OpenAI audio delta empty', { type });
      }
      return;
    }

    if (type === 'response.audio_transcript.delta' || type === 'response.output_audio_transcript.delta') {
      const delta = event.delta ?? '';
      if (typeof delta === 'string') {
        this.currentAssistantText += delta;
        this.onAssistantTranscript?.(delta);
      }
      return;
    }

    if (
      type === 'response.audio_transcript.done' ||
      type === 'response.output_audio_transcript.done'
    ) {
      const text = event.transcript ?? this.currentAssistantText;
      if (text) {
        logInfo('OpenAI assistant said', { text: text.slice(0, 200) });
        this.transcriptLines.push(`[AI] ${text}`);
        this.currentAssistantText = '';
      }
      return;
    }

    if (
      type === 'conversation.item.input_audio_transcription.completed' ||
      type === 'conversation.item.input_audio_transcription.done'
    ) {
      const text = event.transcript ?? '';
      if (text) {
        logInfo('Caller said (transcription)', { text: text.slice(0, 200) });
        this.transcriptLines.push(`[Caller] ${text}`);
        this.onUserTranscript?.(text);
      }
      return;
    }

    if (type === 'response.done') {
      logInfo('OpenAI response.done', {
        status: event.response?.status,
        output_items: event.response?.output?.length,
      });
      return;
    }

    if (type === 'input_audio_buffer.speech_started') {
      logInfo('Caller started speaking (VAD)');
      return;
    }

    if (type === 'input_audio_buffer.speech_stopped') {
      logInfo('Caller stopped speaking (VAD)');
      return;
    }

    if (type === 'response.output_item.added') {
      logDebug('OpenAI output item added', { item: event.item?.type });
      return;
    }

    if (type === 'response.function_call_arguments.delta') {
      const callId = event.call_id;
      if (!callId) return;
      const existing = this.pendingFunctionCalls.get(callId) ?? { name: event.name ?? '', arguments: '' };
      existing.arguments += event.delta ?? '';
      if (event.name) existing.name = event.name;
      this.pendingFunctionCalls.set(callId, existing);
      return;
    }

    if (type === 'response.function_call_arguments.done') {
      this.handleFunctionCall(event.call_id, event.name, event.arguments).catch((err) => {
        logError('Function call handler failed', { message: err.message });
        this.onError?.(err);
      });
      return;
    }

    if (type === 'response.output_item.done' && event.item?.type === 'function_call') {
      this.handleFunctionCall(event.item.call_id, event.item.name, event.item.arguments).catch((err) => {
        logError('Function call handler failed', { message: err.message });
        this.onError?.(err);
      });
      return;
    }
  }

  async handleFunctionCall(callId, name, argsJson) {
    if (!callId || !name || !this.onToolCall) {
      return;
    }

    const dedupeKey = `${callId}:${name}`;
    if (this._handledFunctionCalls?.has(dedupeKey)) {
      return;
    }
    if (!this._handledFunctionCalls) {
      this._handledFunctionCalls = new Set();
    }
    this._handledFunctionCalls.add(dedupeKey);

    let args = {};
    try {
      args = argsJson ? JSON.parse(argsJson) : {};
    } catch {
      args = {};
    }

    logInfo('OpenAI function call', { name, call_id: callId, args_keys: Object.keys(args) });

    let output;
    try {
      output = await this.onToolCall(name, args, callId);
    } catch (err) {
      output = { ok: false, error: err.message || 'Tool execution failed' };
    }

    this.send({
      type: 'conversation.item.create',
      item: {
        type: 'function_call_output',
        call_id: callId,
        output: JSON.stringify(output ?? { ok: false }),
      },
    });

    this.send({
      type: 'response.create',
      response: { output_modalities: ['audio'] },
    });
  }

  sendSessionUpdate() {
    const turnDetection = buildTurnDetection();
    const noiseReduction = buildNoiseReduction();

    logInfo('Sending session.update (GA)', {
      instructions_chars: this.instructions?.length || 0,
      voice: config.openaiVoice,
      model: config.openaiRealtimeModel,
      tools: this.tools?.length ?? 0,
      vad_type: turnDetection.type,
      vad_threshold: turnDetection.threshold ?? null,
      noise_reduction: noiseReduction?.type ?? 'off',
    });

    const input = {
      format: {
        type: 'audio/pcm',
        rate: config.openaiAudioRate,
      },
      turn_detection: turnDetection,
      transcription: {
        model: config.openaiTranscriptionModel,
      },
    };

    if (noiseReduction) {
      input.noise_reduction = noiseReduction;
    }

    const session = {
      type: 'realtime',
      model: config.openaiRealtimeModel,
      instructions: this.instructions,
      output_modalities: ['audio'],
      audio: {
        input,
        output: {
          format: {
            type: 'audio/pcm',
            rate: config.openaiAudioRate,
          },
          voice: config.openaiVoice,
        },
      },
    };

    if (this.tools?.length) {
      session.tools = this.tools;
      session.tool_choice = 'auto';
    }

    this.send({
      type: 'session.update',
      session,
    });
  }

  triggerInitialGreeting(options = {}) {
    logInfo('Triggering initial AI greeting (response.create)');
    if (this.debug) this.debug.initial_greeting_sent = true;

    let instructions =
      'The caller just connected on a WhatsApp voice call. Greet them warmly and briefly.';

    if (options.mentionCapabilityInGreeting && options.capabilityBrief) {
      instructions +=
        ' Then briefly orient them on what you can help with using this brief (paraphrase naturally, keep it to one or two short sentences, do not read a full product list): ' +
        options.capabilityBrief;
    } else {
      instructions += ' Then ask how you can help.';
    }

    this.send({
      type: 'response.create',
      response: {
        output_modalities: ['audio'],
        instructions,
      },
    });
  }

  appendAudioPcm16Base64(base64Audio) {
    if (!this.sessionReady || this.closed) return;
    this.send({
      type: 'input_audio_buffer.append',
      audio: base64Audio,
    });
  }

  getTranscript() {
    const lines = [...this.transcriptLines];
    if (this.currentAssistantText) {
      lines.push(`[AI] ${this.currentAssistantText}`);
    }
    return lines.join('\n');
  }

  send(payload) {
    if (this.closed || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
      logDebug('OpenAI send skipped — socket not open', { type: payload.type, readyState: this.ws?.readyState });
      return;
    }
    this.ws.send(JSON.stringify(payload));
  }

  close() {
    if (this.closed) return;
    this.closed = true;
    try {
      if (this.ws?.readyState === WebSocket.OPEN) {
        this.ws.close();
      }
    } catch {
      /* ignore */
    }
  }
}

function buildTurnDetection() {
  if (config.openaiVadType === 'semantic_vad') {
    return {
      type: 'semantic_vad',
      eagerness: ['low', 'medium', 'high', 'auto'].includes(config.openaiVadEagerness)
        ? config.openaiVadEagerness
        : 'low',
      create_response: true,
      interrupt_response: true,
    };
  }

  const threshold = Number.isFinite(config.openaiVadThreshold)
    ? Math.min(1, Math.max(0, config.openaiVadThreshold))
    : 0.65;

  return {
    type: 'server_vad',
    threshold,
    prefix_padding_ms: config.openaiVadPrefixMs,
    silence_duration_ms: config.openaiVadSilenceMs,
    create_response: true,
    interrupt_response: true,
  };
}

function buildNoiseReduction() {
  const mode = config.openaiNoiseReduction;
  if (!mode || mode === 'off' || mode === 'none' || mode === 'false') {
    return null;
  }
  if (mode === 'far_field' || mode === 'near_field') {
    return { type: mode };
  }
  return { type: 'near_field' };
}
