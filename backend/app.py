from flask import Flask
from flask_cors import CORS
from config import Config
from database import init_pool
from routes.auth import auth_bp

def create_app() -> Flask:
    app = Flask(__name__)
    app.config.from_object(Config)

    # CORS: permite peticiones desde el frontend
    CORS(app, resources={r"/api/*": {"origins": Config.ALLOWED_ORIGINS}})

    # Inicializar pool de base de datos
    with app.app_context():
        init_pool()

    # Registrar blueprints
    app.register_blueprint(auth_bp)

    # Health check
    @app.route("/api/health")
    def health():
        return {"ok": True, "message": "Noxum API corriendo 🚀"}, 200

    return app

if __name__ == "__main__":
    app = create_app()
    print("Servidor Noxum corriendo en http://localhost:5000")
    app.run(debug=True, port=5000)
