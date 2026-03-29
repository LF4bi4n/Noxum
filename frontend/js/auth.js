
import { authAPI, session } from "./api.js";

// ── Helpers UI ─────────────────────────────

function showAlert(el, type, messages) {
  el.className = `auth-alert alert-${type} visible`;
  el.innerHTML = `
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      ${type === "error"
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'}
    </svg>
    <div>${Array.isArray(messages) ? messages.join("<br>") : messages}</div>
  `;
}

function hideAlert(el) {
  el.className = "auth-alert";
  el.textContent = "";
}

function setLoading(btn, state) {
  btn.classList.toggle("loading", state);
  btn.disabled = state;
}

function clearErrors() {
  document.querySelectorAll(".field-error").forEach(el => {
    el.classList.remove("visible");
    el.textContent = "";
  });
  document.querySelectorAll(".form-control").forEach(el => {
    el.classList.remove("error");
  });
}

//Toggle contraseña

document.querySelectorAll(".toggle-pw").forEach(btn => {
  btn.addEventListener("click", () => {
    const target = document.querySelector(btn.dataset.target);
    if (!target) return;
    const isText = target.type === "text";
    target.type = isText ? "password" : "text";
    btn.innerHTML = isText
      ? `<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>`
      : `<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>`;
  });
});

//REGISTRO 

const registerForm = document.getElementById("registerForm");
if (registerForm) {
  registerForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors();

    const alert = document.getElementById("authAlert");
    const btn   = registerForm.querySelector(".btn-submit");
    hideAlert(alert);
    setLoading(btn, true);

    const payload = {
      nombre:   registerForm.nombre.value.trim(),
      email:    registerForm.email.value.trim().toLowerCase(),
      password: registerForm.password.value,
      rol:      registerForm.rol.value
    };

    // Validación cliente rápida
    if (!payload.nombre) {
      registerForm.nombre.classList.add("error");
      document.getElementById("errorNombre").textContent = "El nombre es requerido.";
      document.getElementById("errorNombre").classList.add("visible");
      setLoading(btn, false);
      return;
    }

    const { ok, data } = await authAPI.register(payload);

    if (ok) {
      session.save(data.usuario);
      showAlert(alert, "success", "¡Cuenta creada! Redirigiendo...");
      // Registro público siempre es paciente
      setTimeout(() => {
        window.location.href = "dashboard_paciente.html";
      }, 1200);
    } else {
      const msgs = data.errors || ["Error al crear la cuenta."];
      showAlert(alert, "error", msgs);
    }

    setLoading(btn, false);
  });
}

//LOGIN 

const loginForm = document.getElementById("loginForm");
if (loginForm) {
  if (session.isLoggedIn()) {
    const u = session.getUsuario();
    const rutas = { paciente: 'dashboard_paciente.html', doctor: 'dashboard_doctor.html', recepcionista: 'dashboard_admin.html', admin: 'dashboard_admin.html' };
    window.location.href = rutas[u?.rol] || 'dashboard_paciente.html';
  }

  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors();

    const alert = document.getElementById("authAlert");
    const btn   = loginForm.querySelector(".btn-submit");
    hideAlert(alert);
    setLoading(btn, true);

    const payload = {
      email:    loginForm.email.value.trim().toLowerCase(),
      password: loginForm.password.value
    };

    const { ok, data } = await authAPI.login(payload);

    if (ok) {
      session.save(data.usuario);
      showAlert(alert, "success", `¡Bienvenido, ${data.usuario.nombre}!`);

      // Redirigir según el rol que devuelve el servidor
      const rutas = {
        'paciente':      'dashboard_paciente.html',
        'doctor':        'dashboard_doctor.html',
        'recepcionista': 'dashboard_admin.html',
        'admin':         'dashboard_admin.html'
      };
      const destino = rutas[data.usuario.rol] || 'login.html';

      setTimeout(() => {
        window.location.href = destino;
      }, 1000);
    } else {
      const msgs = data.errors || ["Credenciales incorrectas."];
      showAlert(alert, "error", msgs);
    }

    setLoading(btn, false);
  });
}
