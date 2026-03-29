// guard.js — Protección de páginas por rol
// Incluir al inicio de cada dashboard ANTES de cualquier otro script.
//
// Uso:
//   <script src="js/guard.js" data-rol="doctor"></script>
//   <script src="js/guard.js" data-rol="paciente"></script>
//   <script src="js/guard.js" data-rol="recepcionista"></script>

(async () => {
  const API = 'http://localhost/noxum/backend/api';

  const rolRequerido = document.currentScript?.dataset?.rol;

  const rutas = {
    'paciente':      'dashboard_paciente.html',
    'doctor':        'dashboard_doctor.html',
    'recepcionista': 'dashboard_admin.html',
    'admin':         'dashboard_admin.html'
  };

  // Roles permitidos por dashboard
  // dashboard_admin acepta tanto 'admin' como 'recepcionista'
  const acceso = {
    'paciente':      ['paciente'],
    'doctor':        ['doctor'],
    'recepcionista': ['recepcionista', 'admin']
  };

  try {
    // Verificar sesión activa con el servidor (protección real)
    const res  = await fetch(`${API}/login.php?check`, { credentials: 'include' });
    const data = await res.json().catch(() => ({}));

    if (!data.ok) {
      // Sin sesión → login
      window.location.replace('login.html');
      return;
    }

    const rolUsuario = data.usuario.rol;

    // Guardar en sessionStorage para uso en la página
    sessionStorage.setItem('noxum_usuario', JSON.stringify(data.usuario));

    // Verificar si el rol tiene acceso a esta página
    const rolesPermitidos = acceso[rolRequerido] || [rolRequerido];
    if (rolRequerido && !rolesPermitidos.includes(rolUsuario)) {
      window.location.replace(rutas[rolUsuario] || 'login.html');
    }

  } catch {
    // Error de red → login
    window.location.replace('login.html');
  }
})();
