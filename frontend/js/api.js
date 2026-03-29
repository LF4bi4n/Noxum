
const API_BASE = "http://localhost/noxum/backend/api";

async function apiFetch(endpoint, options = {}) {
    const url = `${API_BASE}${endpoint}`;

    try {
        const res = await fetch(url, {
            ...options,
            credentials: "include",           
            headers: { "Content-Type": "application/json", ...(options.headers || {}) }
        });

        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };

    } catch (err) {
        console.error("[Noxum API] Error de red:", err);
        return {
            ok: false,
            status: 0,
            data: { errors: ["No se pudo conectar con el servidor. Verifica que XAMPP esté corriendo."] }
        };
    }
}

//Auth

export const authAPI = {
    register(payload) {
        return apiFetch("/register.php", {
            method: "POST",
            body: JSON.stringify(payload)
        });
    },

    login(payload) {
        return apiFetch("/login.php", {
            method: "POST",
            body: JSON.stringify(payload)
        });
    },

    logout() {
        return apiFetch("/login.php?logout", { method: "POST" });
    },

    checkSession() {
        return apiFetch("/login.php?check", { method: "GET" });
    }
};


export const session = {
    save(usuario) {
        sessionStorage.setItem("noxum_usuario", JSON.stringify(usuario));
    },
    clear() {
        sessionStorage.removeItem("noxum_usuario");
    },
    getUsuario() {
        try { return JSON.parse(sessionStorage.getItem("noxum_usuario")); }
        catch { return null; }
    },
    isLoggedIn() {
        return !!sessionStorage.getItem("noxum_usuario");
    }
};
