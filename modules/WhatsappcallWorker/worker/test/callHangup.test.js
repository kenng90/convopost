import test from 'node:test';
import assert from 'node:assert/strict';
import { waitForCallEnd } from '../src/callHangup.js';

test('waitForCallEnd resolves when Laravel reports terminate status', async () => {
  let polls = 0;
  const laravel = {
    show: async () => {
      polls += 1;
      if (polls >= 2) {
        return { call: { status: 'terminate' } };
      }
      return { call: { status: 'accept' } };
    },
  };

  const peerConnection = {
    connectionState: 'connected',
    iceConnectionState: 'connected',
    signalingState: 'stable',
    addEventListener() {},
    removeEventListener() {},
    getReceivers() {
      return [];
    },
  };

  await waitForCallEnd({ peerConnection, laravel, remoteTrack: null });
  assert.ok(polls >= 2);
});
