import { config } from './config.js';
import { OpenAIRealtimeClient } from './openaiRealtimeClient.js';
import { AudioBridge } from './audioBridge.js';
import { logInfo, logError, logWarn } from './logger.js';
import { addError } from './sessionDebug.js';
import { buildSuccessCompletionPayload } from './callCompletion.js';
import { waitForCallEnd } from './callHangup.js';

/**
 * Production OpenAI Realtime voice session for a connected WhatsApp call.
 * Returns a completion payload for Laravel (posted by incomingHandler).
 */
export async function runRealtimeCallSession({ payload, laravel, startedAt, peerConnection, audioSource, debug }) {
  if (!config.openaiApiKey) {
    throw new Error('OPENAI_API_KEY is required for realtime mode');
  }
  if (!audioSource) {
    throw new Error('Audio source missing — WebRTC outbound track not configured');
  }

  const instructions = buildInstructions(payload);
  logInfo('Realtime session starting', {
    instructions_chars: instructions.length,
    flow_id: payload.flow_id,
    has_system_context: Boolean(payload.system_context),
    has_vector_context: Boolean(payload.vector_context),
  });

  const realtime = new OpenAIRealtimeClient({ instructions, debug });
  let bridge = null;
  let remoteTrack = null;
  let ended = false;
  let completionPayload = null;

  const endCall = async (reason) => {
    if (ended) {
      return completionPayload;
    }
    ended = true;

    logInfo('Realtime session ending', {
      reason,
      duration_s: Math.round((Date.now() - startedAt) / 1000),
      audio_in_frames: debug.audio_in_frames,
      audio_out_chunks: debug.audio_out_chunks,
      openai_session_ready: debug.openai_session_ready,
      errors: debug.errors.length,
    });

    if (debug.audio_out_chunks === 0) {
      logWarn('No OpenAI audio was sent to the caller — check OPENAI API key, model access, and worker logs above');
    }

    bridge?.stop();
    realtime.close();

    const transcript = realtime.getTranscript() || `Call ended (${reason}).`;
    completionPayload = buildSuccessCompletionPayload({
      payload,
      startedAt,
      debug,
      transcript,
      reason,
    });

    return completionPayload;
  };

  try {
    await realtime.connect();
    bridge = new AudioBridge({ peerConnection, audioSource, realtimeClient: realtime, debug });
    remoteTrack = await bridge.start();

    await sleep(400);
    realtime.triggerInitialGreeting();

    logInfo('Waiting for caller hangup (WebRTC + Laravel status poll)');
    await waitForCallEnd({ peerConnection, laravel, remoteTrack });

    return await endCall('completed');
  } catch (err) {
    logError('Realtime session error', { message: err.message, stack: err.stack?.split('\n').slice(0, 3) });
    addError(debug, err.message);
    if (!ended) {
      return await endCall(`error:${err.message}`);
    }
    return completionPayload;
  } finally {
    bridge?.stop();
    realtime.close();
    if (!ended) {
      logWarn('Realtime session ended without endCall — building completion from finally');
      await endCall('completed');
    }
  }
}

function buildInstructions(payload) {
  const sections = [];

  if (payload.system_context) {
    sections.push(payload.system_context);
  }
  if (payload.vector_context) {
    sections.push(payload.vector_context);
  }

  sections.push(
    'You are the company voice agent on a live WhatsApp phone call.',
    'Speak naturally, keep answers concise (1–3 sentences unless the caller asks for detail).',
    'Use only the knowledge provided above — if unsure, say so and offer to follow up in chat.',
    'When the caller is done or says goodbye, thank them and end the conversation politely.',
  );

  if (payload.ai_greeting) {
    sections.push(`Your opening greeting should be based on: ${payload.ai_greeting}`);
  }

  const required = payload.required_field_keys ?? [];
  if (required.length) {
    sections.push(
      `During the call, try to collect these details if relevant: ${required.join(', ')}.`,
    );
  }

  return sections.join('\n\n');
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}
