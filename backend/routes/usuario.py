import bcrypt
import re
from database import execute_query
from mysql.connector import Error

EMAIL_REGEX = re.compile(r"^[\w\.-]+@[\w\.-]+\.\w{2,}$")

# Helpers
def hash_password(plain: str) -> str:
    return bcrypt.hashpw(plain.encode(), bcrypt.gensalt(12)).decode()

def verify_password(plain: str, hashed: str) -> bool:
    return bcrypt.checkpw(plain.encode(), hashed.encode())

def validate_registro(data: dict) -> list[str]:
    """Valida los campos del registro. Retorna lista de errores."""
    errors = []
    nombre   = (data.get("nombre")   or "").strip()
    email    = (data.get("email")    or "").strip()
    password = (data.get("password") or "").strip()
    rol      = (data.get("rol")      or "").strip()

    if not nombre or len(nombre) < 2:
        errors.append("El nombre debe tener al menos 2 caracteres.")
    if not EMAIL_REGEX.match(email):
        errors.append("El email no es válido.")
    if len(password) < 8:
        errors.append("La contraseña debe tener al menos 8 caracteres.")
    if rol not in ("paciente", "doctor", "recepcionista"):
        errors.append("Rol no válido.")
    return errors

# CRUD
def crear_usuario(nombre: str, email: str, password: str, rol: str) -> dict:

    # Verificar email duplicado
    existente = execute_query(
        "SELECT id_usuario FROM usuarios WHERE email = %s",
        (email,), fetch=True
    )
    if existente:
        raise ValueError("Ya existe una cuenta con ese email.")

    hashed = hash_password(password)
    id_usuario = execute_query(
        "INSERT INTO usuarios (nombre, email, password, rol) VALUES (%s, %s, %s, %s)",
        (nombre, email, hashed, rol)
    )

    # Insertar en tabla del rol
    if rol == "paciente":
        execute_query(
            "INSERT INTO pacientes (id_usuario) VALUES (%s)", (id_usuario,)
        )
    elif rol == "doctor":
        execute_query(
            "INSERT INTO doctores (id_usuario, especialidad) VALUES (%s, %s)",
            (id_usuario, "Psicología")
        )
    elif rol == "recepcionista":
        execute_query(
            "INSERT INTO recepcionistas (id_usuario) VALUES (%s)", (id_usuario,)
        )

    return obtener_usuario_por_id(id_usuario)

def obtener_usuario_por_id(id_usuario: int) -> dict | None:
    rows = execute_query(
        "SELECT id_usuario, nombre, email, rol, estado FROM usuarios WHERE id_usuario = %s",
        (id_usuario,), fetch=True
    )
    return rows[0] if rows else None

def obtener_usuario_por_email(email: str) -> dict | None:
    """Retorna el usuario CON hash (para login)."""
    rows = execute_query(
        "SELECT id_usuario, nombre, email, password, rol, estado FROM usuarios WHERE email = %s",
        (email,), fetch=True
    )
    return rows[0] if rows else None
