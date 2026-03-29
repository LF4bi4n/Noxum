from flask import Blueprint, request, jsonify
import jwt
import datetime
import sys, os
sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))

from config import Config
from models.usuario import (
    crear_usuario, obtener_usuario_por_email,
    validate_registro, verify_password
)

auth_bp = Blueprint("auth", __name__, url_prefix="/api/auth")

def make_token(id_usuario: int, rol: str) -> str:
    payload = {
        "sub": id_usuario,
        "rol": rol,
        "exp": datetime.datetime.utcnow() + datetime.timedelta(hours=Config.JWT_EXPIRY_HOURS)
    }
    return jwt.encode(payload, Config.SECRET_KEY, algorithm="HS256")

# ─────────────────────────────────────────
# POST /api/auth/register
# ─────────────────────────────────────────
@auth_bp.route("/register", methods=["POST"])
def register():
    data = request.get_json(silent=True) or {}

    # Validaciones
    errors = validate_registro(data)
    if errors:
        return jsonify({"ok": False, "errors": errors}), 422

    try:
        usuario = crear_usuario(
            nombre   = data["nombre"].strip(),
            email    = data["email"].strip().lower(),
            password = data["password"].strip(),
            rol      = data["rol"].strip()
        )
    except ValueError as e:
        return jsonify({"ok": False, "errors": [str(e)]}), 409
    except Exception as e:
        return jsonify({"ok": False, "errors": ["Error interno del servidor."]}), 500

    token = make_token(usuario["id_usuario"], usuario["rol"])
    return jsonify({"ok": True, "token": token, "usuario": usuario}), 201

# ─────────────────────────────────────────
# POST /api/auth/login
# ─────────────────────────────────────────
@auth_bp.route("/login", methods=["POST"])
def login():
    data = request.get_json(silent=True) or {}
    email    = (data.get("email")    or "").strip().lower()
    password = (data.get("password") or "").strip()

    if not email or not password:
        return jsonify({"ok": False, "errors": ["Email y contraseña requeridos."]}), 422

    usuario = obtener_usuario_por_email(email)
    if not usuario or not verify_password(password, usuario["password"]):
        return jsonify({"ok": False, "errors": ["Credenciales incorrectas."]}), 401

    if usuario["estado"] == "inactivo":
        return jsonify({"ok": False, "errors": ["Cuenta desactivada."]}), 403

    token = make_token(usuario["id_usuario"], usuario["rol"])
    usuario_publico = {k: v for k, v in usuario.items() if k != "password"}
    return jsonify({"ok": True, "token": token, "usuario": usuario_publico}), 200
