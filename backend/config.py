import os

class Config:
    # Base de datos MySQL con XAMPP
    DB_HOST     = os.getenv("DB_HOST",     "localhost")
    DB_PORT     = int(os.getenv("DB_PORT", 3306))
    DB_USER     = os.getenv("DB_USER",     "root")
    DB_PASSWORD = os.getenv("DB_PASSWORD", "")          # XAMPP default: sin contraseña
    DB_NAME     = os.getenv("DB_NAME",     "noxum_db")

    # JWT
    SECRET_KEY       = os.getenv("SECRET_KEY", "noxum_secret_super_seguro_2024")
    JWT_EXPIRY_HOURS = 24

    # CORS
    ALLOWED_ORIGINS = ["http://127.0.0.1:5500", "http://localhost:5500",
                       "http://localhost:3000", "http://127.0.0.1:3000"]
