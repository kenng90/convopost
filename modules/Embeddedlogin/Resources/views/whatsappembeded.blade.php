<script>
  window.embeddedSignupOptions = @json($signupOptions ?? []);

  window.fbAsyncInit = function() {
    FB.init({
      appId: '{{ config("services.facebook.app_id","") }}',
      autoLogAppEvents: true,
      cookie: true,
      xfbml: true,
      version: '{{ config("embeddedlogin.graph_version", "v22.0") }}'
    });
  };

  (function (d, s, id) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) return;
    js = d.createElement(s); js.id = id;
    js.src = "https://connect.facebook.net/en_US/sdk.js";
    fjs.parentNode.insertBefore(js, fjs);
  }(document, 'script', 'facebook-jssdk'));

  var embeddedSignupSession = {
    flow: 'whatsapp_only',
    waba_id: null,
    phone_number_id: null,
    page_id: null,
    instagram_account_id: null,
    page_ids: null,
    instagram_account_ids: null,
  };

  window.addEventListener('message', function (event) {
    if (!event.origin || !event.origin.endsWith('facebook.com')) {
      return;
    }

    try {
      var data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
      if (!data || data.type !== 'WA_EMBEDDED_SIGNUP') {
        return;
      }

      if (data.event === 'CANCEL') {
        console.warn('Embedded signup cancelled', data.data || data);
        resetEmbeddedButtons();
        return;
      }

      if (data.event === 'ERROR') {
        console.error('Embedded signup ERROR event', data);
        resetEmbeddedButtons();
        return;
      }

      if (data.event === 'FINISH' && data.data) {
        embeddedSignupSession.waba_id = data.data.waba_id || embeddedSignupSession.waba_id;
        embeddedSignupSession.phone_number_id = data.data.phone_number_id || embeddedSignupSession.phone_number_id;
        embeddedSignupSession.page_id = data.data.page_id || embeddedSignupSession.page_id;
        embeddedSignupSession.instagram_account_id = data.data.instagram_account_id || embeddedSignupSession.instagram_account_id;
        embeddedSignupSession.page_ids = data.data.page_ids || embeddedSignupSession.page_ids;
        embeddedSignupSession.instagram_account_ids = data.data.instagram_account_ids || embeddedSignupSession.instagram_account_ids;
      }
    } catch (e) {
      console.log('WA_EMBEDDED_SIGNUP raw message:', event.data);
    }
  });

  function resetEmbeddedButtons() {
    document.querySelectorAll('.js-embedded-signup-btn').forEach(function(btn) {
      btn.style.display = '';
      btn.disabled = false;
    });
    var anim = document.getElementById('anim');
    if (anim) {
      anim.style.display = 'none';
    }
  }

  function buildSignupExtras(flow) {
    // WhatsApp-only: match the pre-omni extras shape. Forcing sessionInfoVersion:3
    // on a WhatsApp-only Login config often surfaces Meta's "Feature unavailable".
    var extras = {};

    if (flow === 'omnichannel') {
      extras.sessionInfoVersion = window.embeddedSignupOptions.session_info_version || '3';
    }

    if (window.embeddedSignupOptions.solution_id) {
      extras.setup = {
        solutionID: window.embeddedSignupOptions.solution_id
      };
    }

    return extras;
  }

  function launchEmbeddedSignup(flow) {
    if (typeof FB === 'undefined' || typeof FB.login !== 'function') {
      alert('{{ __("Facebook SDK failed to load. Refresh and allow scripts from connect.facebook.net.") }}');
      return;
    }

    var configId = flow === 'omnichannel'
      ? window.embeddedSignupOptions.omni_config_id
      : window.embeddedSignupOptions.whatsapp_config_id;

    if (!configId) {
      alert('{{ __("Embedded Signup is not configured for this flow.") }}');
      return;
    }

    if (
      flow === 'omnichannel'
      && configId === window.embeddedSignupOptions.whatsapp_config_id
      && window.embeddedSignupOptions.omni_config_id === window.embeddedSignupOptions.whatsapp_config_id
    ) {
      console.warn('Omnichannel button is using the same Config ID as WhatsApp-only. Create a separate Facebook Login for Business configuration that includes Pages/Instagram.');
    }

    embeddedSignupSession.flow = flow;
    embeddedSignupSession.waba_id = null;
    embeddedSignupSession.phone_number_id = null;
    embeddedSignupSession.page_id = null;
    embeddedSignupSession.instagram_account_id = null;
    embeddedSignupSession.page_ids = null;
    embeddedSignupSession.instagram_account_ids = null;

    document.querySelectorAll('.js-embedded-signup-btn').forEach(function(btn) {
      btn.style.display = 'none';
    });
    document.getElementById('anim').style.display = 'block';

    var loginOptions = {
      config_id: String(configId),
      response_type: 'code',
      override_default_response_type: true,
      extras: buildSignupExtras(flow)
    };

    console.log('Launching Embedded Signup', {
      flow: flow,
      config_id: loginOptions.config_id,
      extras: loginOptions.extras,
      appId: '{{ config("services.facebook.app_id","") }}'
    });

    FB.login(function (response) {
      if (!response.authResponse) {
        console.warn('Embedded Signup closed without authResponse', response);
        resetEmbeddedButtons();
        return;
      }

      var code = response.authResponse.code;
      var params = new URLSearchParams();
      params.set('flow', flow);

      ['waba_id', 'phone_number_id', 'page_id', 'instagram_account_id'].forEach(function(key) {
        if (embeddedSignupSession[key]) {
          params.set(key, embeddedSignupSession[key]);
        }
      });

      if (embeddedSignupSession.page_ids) {
        params.set('page_ids', JSON.stringify(embeddedSignupSession.page_ids));
      }
      if (embeddedSignupSession.instagram_account_ids) {
        params.set('instagram_account_ids', JSON.stringify(embeddedSignupSession.instagram_account_ids));
      }

      $.ajax({
        url: '/embeddedlogin/api/' + encodeURIComponent(code) + '?' + params.toString(),
        type: 'GET',
        success: function(result) {
          document.getElementById('anim').style.display = 'none';

          if (result.status === 'success') {
            document.getElementById('success').style.display = 'block';
            setTimeout(function() {
              window.location.href = '/home';
            }, 2500);
          } else {
            resetEmbeddedButtons();
            alert('Error: ' + (result.error || 'Unknown error'));
          }
        },
        error: function() {
          document.getElementById('anim').style.display = 'none';
          resetEmbeddedButtons();
          alert('Error completing signup.');
        }
      });
    }, loginOptions);
  }

  function launchWhatsAppOnlySignup() {
    launchEmbeddedSignup('whatsapp_only');
  }

  function launchOmnichannelSignup() {
    launchEmbeddedSignup('omnichannel');
  }

  // Back-compat for older docs / bookmarks that call launchWhatsAppSignup()
  function launchWhatsAppSignup() {
    launchWhatsAppOnlySignup();
  }
</script>

<div class="container py-3">
  <script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script>
  <dotlottie-player id="anim" src="https://lottie.host/1c4e7c05-b40d-4064-b940-e5d1040b2019/bCu4S9tJ4s.json" background="transparent" speed="1" style="width: 200px; height: 200px; display: none; margin: 0 auto;" loop autoplay></dotlottie-player>
  <dotlottie-player id="success" src="https://lottie.host/c46f068d-2568-46ad-a622-2f9011b0252c/Pcs4qIKy3C.json" background="transparent" speed="1" style="width: 300px; height: 300px; display: none; margin: 0 auto;" loop autoplay></dotlottie-player>

  <div class="d-flex flex-column gap-3 align-items-stretch" style="max-width: 420px; margin: 0 auto;">
    <p class="text-muted text-sm mb-0 text-center">
      {{ __('Connect via Meta Embedded Signup. Choose WhatsApp only, or WhatsApp + Instagram + Messenger when omnichannel is configured.') }}
    </p>

    <button id="embButton" onclick="launchWhatsAppOnlySignup()" type="button" class="btn btn-success btn-whatsapp js-embedded-signup-btn">
      @if ($setupDone ?? false)
        {{ __('Re-connect WhatsApp') }}
      @else
        {{ __('WhatsApp Setup') }}
      @endif
    </button>

    @if (!empty($signupOptions['omnichannel_available']))
      <button id="embButtonOmni" onclick="launchOmnichannelSignup()" type="button" class="btn btn-primary js-embedded-signup-btn">
        {{ __('Connect WhatsApp + Instagram + Messenger') }}
      </button>
      <p class="text-muted text-xs text-center mb-0">{{ __('Uses your omnichannel Meta configuration. Requires inbox_instagram or inbox_messenger on your plan.') }}</p>
    @endif
  </div>
</div>
