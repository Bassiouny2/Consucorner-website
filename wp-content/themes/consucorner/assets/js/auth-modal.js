(function () {
  "use strict";

  var CFG = window.consuAuthData || {};

  var MODAL_HTML = [
    '<div class="cc-auth-modal" id="cc-auth-modal" hidden>',
    '  <div class="cc-auth-backdrop" data-auth-close></div>',
    '  <section class="cc-auth-dialog" role="dialog" aria-modal="true" aria-labelledby="cc-auth-title-login">',

    /* close button */
    '    <button type="button" class="cc-auth-close" data-auth-close aria-label="Close">&#x2715;</button>',

    /* ---- LOGIN VIEW ---- */
    '    <div class="cc-auth-view" data-auth-view="login">',
    '      <h2 class="cc-auth-title" id="cc-auth-title-login">Log in</h2>',
    '      <form class="cc-auth-form" data-auth-form="login" novalidate>',

    '        <label class="cc-auth-label" for="cc-login-email">Email address or user name</label>',
    '        <input class="cc-auth-input" id="cc-login-email" name="email" type="text" autocomplete="username email" placeholder="Enter your email or username" />',

    '        <label class="cc-auth-label" for="cc-login-password">Password</label>',
    '        <div class="cc-auth-password-wrap">',
    '          <input class="cc-auth-input" id="cc-login-password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" />',
    '          <button type="button" class="cc-auth-eye" data-toggle-password="cc-login-password">Hide</button>',
    '        </div>',

    '        <label class="cc-auth-check-row">',
    '          <input type="checkbox" name="remember" checked />',
    '          <span>Remember me</span>',
    '        </label>',

    '        <p class="cc-auth-copy">By continuing, you agree to the <a href="#" data-auth-terms>Terms of use</a> and <a href="#" data-auth-privacy>Privacy Policy</a>.</p>',
    '        <p class="cc-auth-message" data-auth-message="login" aria-live="polite"></p>',

    '        <button type="submit" class="cc-auth-submit active">Log in</button>',
    '      </form>',
    '      <a href="#" class="cc-auth-link" data-auth-switch="forgot">Forget your password</a>',
    '      <p class="cc-auth-footnote">Don\'t have an account? <a href="#" data-auth-switch="signup">Sign up</a></p>',
    '    </div>',

    /* ---- FORGOT PASSWORD VIEW ---- */
    '    <div class="cc-auth-view" data-auth-view="forgot" hidden>',
    '      <h2 class="cc-auth-title" id="cc-auth-title-forgot">Reset password</h2>',
    '      <p class="cc-auth-copy">Enter your email address or username and we will send you a password reset link.</p>',
    '      <form class="cc-auth-form" data-auth-form="forgot" novalidate>',
    '        <label class="cc-auth-label" for="cc-forgot-login">Email address or user name</label>',
    '        <input class="cc-auth-input" id="cc-forgot-login" name="user_login" type="text" autocomplete="username email" placeholder="Enter your email or username" />',
    '        <p class="cc-auth-message" data-auth-message="forgot" aria-live="polite"></p>',
    '        <button type="submit" class="cc-auth-submit active">Send reset link</button>',
    '      </form>',
    '      <p class="cc-auth-footnote">Remember your password? <a href="#" data-auth-switch="login">Log in</a></p>',
    '    </div>',

    /* ---- SIGNUP VIEW ---- */
    '    <div class="cc-auth-view" data-auth-view="signup" hidden>',
    '      <h2 class="cc-auth-title" id="cc-auth-title-signup">Sign up for free shopping</h2>',

    '      <div class="cc-auth-socials">',
    '        <button type="button" class="cc-auth-social-btn">',
    '          <span class="cc-social-icon cc-social-icon--facebook">f</span>',
    '          <span>Sign up with Facebook</span>',
    '        </button>',
    '        <button type="button" class="cc-auth-social-btn">',
    '          <span class="cc-social-icon cc-social-icon--google">G</span>',
    '          <span>Sign up with Google</span>',
    '        </button>',
    '      </div>',

    '      <div class="cc-auth-divider"><span>OR</span></div>',
    '      <p class="cc-auth-section-title">Sign up with your email address</p>',

    '      <form class="cc-auth-form cc-auth-signup-form" data-auth-form="signup" novalidate>',
    '        <label class="cc-auth-label" for="cc-signup-profile">Profile name</label>',
    '        <input class="cc-auth-input" id="cc-signup-profile" name="profile_name" type="text" autocomplete="name" placeholder="Enter your profile name" />',

    '        <label class="cc-auth-label" for="cc-signup-username">User name</label>',
    '        <input class="cc-auth-input" id="cc-signup-username" name="user_name" type="text" autocomplete="username" placeholder="Enter your username" />',

    '        <label class="cc-auth-label" for="cc-signup-email">Email</label>',
    '        <input class="cc-auth-input" id="cc-signup-email" name="email" type="email" autocomplete="email" placeholder="Enter your email address" />',

    '        <label class="cc-auth-label" for="cc-signup-phone">Phone number</label>',
    '        <input class="cc-auth-input" id="cc-signup-phone" name="phone" type="tel" autocomplete="tel" placeholder="Enter your phone number" />',

    '        <label class="cc-auth-label" for="cc-signup-password">Password</label>',
    '        <div class="cc-auth-password-wrap">',
    '          <input class="cc-auth-input" id="cc-signup-password" name="password" type="password" autocomplete="new-password" placeholder="Enter your password" />',
    '          <button type="button" class="cc-auth-eye" data-toggle-password="cc-signup-password">Hide</button>',
    '        </div>',
    '        <p class="cc-auth-help">Use 8 or more characters with a mix of letters, numbers &amp; symbols</p>',

    '        <label class="cc-auth-check-row">',
    '          <input type="checkbox" checked />',
    '          <span>Share my registration data with our content providers for marketing purposes.</span>',
    '        </label>',

    '        <p class="cc-auth-copy">By creating an account, you agree to the <a href="#" data-auth-terms>Terms of use</a> and <a href="#" data-auth-privacy>Privacy Policy</a>.</p>',
    '        <p class="cc-auth-message" data-auth-message="signup" aria-live="polite"></p>',

    '        <button type="submit" class="cc-auth-submit active">Sign up</button>',
    '      </form>',

    '      <p class="cc-auth-footnote">Already have an account? <a href="#" data-auth-switch="login">Log in</a></p>',
    '    </div>',

    '  </section>',
    '</div>'
  ].join("\n");

  function init() {
    /* Inject modal HTML into page */
    var wrapper = document.createElement("div");
    wrapper.innerHTML = MODAL_HTML;
    document.body.appendChild(wrapper.firstElementChild);

    var modal      = document.getElementById("cc-auth-modal");
    var dialog     = modal.querySelector(".cc-auth-dialog");
    var loginView  = modal.querySelector('[data-auth-view="login"]');
    var signupView = modal.querySelector('[data-auth-view="signup"]');
    var forgotView = modal.querySelector('[data-auth-view="forgot"]');

    modal.querySelectorAll("[data-auth-terms]").forEach(function (link) {
      link.href = CFG.termsUrl || "/terms-and-conditions/";
    });
    modal.querySelectorAll("[data-auth-privacy]").forEach(function (link) {
      link.href = CFG.privacyUrl || "/privacy-policy/";
    });

    /* ---- helpers ---- */
    function setView(view) {
      var toSignup = view === "signup";
      var toForgot = view === "forgot";
      loginView.hidden  = toSignup || toForgot;
      signupView.hidden = !toSignup;
      forgotView.hidden = !toForgot;
      /* always scroll dialog back to top on view change */
      dialog.scrollTop = 0;
      dialog.setAttribute(
        "aria-labelledby",
        toSignup ? "cc-auth-title-signup" : (toForgot ? "cc-auth-title-forgot" : "cc-auth-title-login")
      );
      clearMessages();
    }

    function clearMessages() {
      modal.querySelectorAll(".cc-auth-message").forEach(function (el) {
        el.textContent = "";
        el.classList.remove("is-error", "is-success");
      });
    }

    function setMessage(type, message, isError) {
      var box = modal.querySelector('[data-auth-message="' + type + '"]');
      if (!box) return;
      box.textContent = message || "";
      box.classList.toggle("is-error", !!isError);
      box.classList.toggle("is-success", !isError && !!message);
    }

    function setSubmitting(form, on) {
      var btn = form.querySelector(".cc-auth-submit");
      if (!btn) return;
      btn.disabled = !!on;
      btn.textContent = on ? "Please wait..." : (btn.getAttribute("data-original-text") || btn.textContent);
    }

    function postAuth(action, fields) {
      var body = new URLSearchParams();
      body.set("action", action);
      body.set("nonce", CFG.nonce || "");
      Object.keys(fields).forEach(function (key) {
        body.set(key, fields[key]);
      });

      return fetch(CFG.ajaxUrl || "/wp-admin/admin-ajax.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
        credentials: "same-origin"
      }).then(function (response) {
        return response.json();
      }).then(function (json) {
        if (!json || !json.success) {
          throw new Error((json && json.data && json.data.message) || "Request failed. Please try again.");
        }
        return json.data || {};
      });
    }

    function openModal(view) {
      setView(view || "login");
      modal.hidden = false;
      dialog.scrollTop = 0;
      document.body.classList.add("auth-modal-open");
      var first = modal.querySelector('.cc-auth-view:not([hidden]) .cc-auth-input');
      if (first) setTimeout(function () { first.focus(); }, 60);
    }

    function closeModal() {
      modal.hidden = true;
      document.body.classList.remove("auth-modal-open");
    }

    /* ---- open triggers (header button + mobile drawer) ---- */
    document.addEventListener("click", function (e) {
      var accountLink = e.target.closest(".auth-account-link, .drawer-account-link");
      if (accountLink) {
        return;
      }

      if (e.target.closest(".auth-link") || e.target.closest(".drawer-login-btn")) {
        e.preventDefault();
        openModal("login");
        return;
      }
      if (e.target.closest("[data-auth-close]")) {
        closeModal();
        return;
      }
      var switcher = e.target.closest("[data-auth-switch]");
      if (switcher) {
        e.preventDefault();
        setView(switcher.getAttribute("data-auth-switch"));
        return;
      }
      var toggle = e.target.closest("[data-toggle-password]");
      if (toggle) {
        var inputId = toggle.getAttribute("data-toggle-password");
        var input   = inputId ? document.getElementById(inputId) : null;
        if (!input) return;
        var reveal  = input.type === "password";
        input.type  = reveal ? "text" : "password";
        toggle.textContent = reveal ? "Hide" : "Show";
        return;
      }
    });

    /* Prevent background scroll-click on backdrop from doing nothing weird */
    modal.addEventListener("click", function (e) {
      if (e.target === modal) closeModal();
    });

    /* Keyboard close */
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.hidden) closeModal();
    });

    modal.querySelectorAll(".cc-auth-submit").forEach(function (btn) {
      btn.setAttribute("data-original-text", btn.textContent);
    });

    /* Real WordPress auth submits. */
    modal.addEventListener("submit", function (e) {
      e.preventDefault();
      var form = e.target.closest("[data-auth-form]");
      if (!form) return;

      var type = form.getAttribute("data-auth-form");
      var data = new FormData(form);
      var action = "";
      var fields = {};

      clearMessages();

      if (type === "login") {
        action = "consucorner_auth_login";
        fields = {
          login: (data.get("email") || "").toString().trim(),
          password: (data.get("password") || "").toString(),
          remember: data.get("remember") ? "1" : ""
        };
        if (!fields.login || !fields.password) {
          setMessage(type, "Please enter your email/username and password.", true);
          return;
        }
      } else if (type === "signup") {
        action = "consucorner_auth_signup";
        fields = {
          profile_name: (data.get("profile_name") || "").toString().trim(),
          user_name: (data.get("user_name") || "").toString().trim(),
          email: (data.get("email") || "").toString().trim(),
          phone: (data.get("phone") || "").toString().trim(),
          password: (data.get("password") || "").toString()
        };
        if (!fields.profile_name || !fields.user_name || !fields.email || !fields.password) {
          setMessage(type, "Please fill in name, username, email, and password.", true);
          return;
        }
      } else if (type === "forgot") {
        action = "consucorner_auth_lost_password";
        fields = {
          user_login: (data.get("user_login") || "").toString().trim()
        };
        if (!fields.user_login) {
          setMessage(type, "Please enter your email address or username.", true);
          return;
        }
      } else {
        return;
      }

      setSubmitting(form, true);
      postAuth(action, fields)
        .then(function (payload) {
          setMessage(type, payload.message || "Success.", false);
          if (payload.redirectUrl) {
            setTimeout(function () {
              window.location.href = payload.redirectUrl || CFG.accountUrl || "/my-account/";
            }, 700);
          }
        })
        .catch(function (err) {
          setMessage(type, err.message, true);
        })
        .finally(function () {
          setSubmitting(form, false);
        });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
