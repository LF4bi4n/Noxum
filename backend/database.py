import mysql.connector
from mysql.connector import Error, pooling
from config import Config

# Pool de conexiones para mejor rendimiento
connection_pool = None

def init_pool():
    global connection_pool
    try:
        connection_pool = pooling.MySQLConnectionPool(
            pool_name="noxum_pool",
            pool_size=5,
            host=Config.DB_HOST,
            port=Config.DB_PORT,
            user=Config.DB_USER,
            password=Config.DB_PASSWORD,
            database=Config.DB_NAME,
            charset="utf8mb4",
            collation="utf8mb4_unicode_ci"
        )
        print("Pool de conexiones MySQL iniciado correctamente")
    except Error as e:
        print(f"Error al crear el pool de conexiones: {e}")
        raise

def get_connection():
    """Obtiene una conexión del pool."""
    global connection_pool
    if connection_pool is None:
        init_pool()
    return connection_pool.get_connection()

def execute_query(query: str, params: tuple = None, fetch: bool = False):
    """
    Ejecuta un query de forma segura.
    - fetch=True  → retorna filas (SELECT)
    - fetch=False → retorna lastrowid (INSERT/UPDATE/DELETE)
    """
    conn = None
    cursor = None
    try:
        conn = get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params or ())

        if fetch:
            result = cursor.fetchall()
        else:
            conn.commit()
            result = cursor.lastrowid

        return result
    except Error as e:
        if conn:
            conn.rollback()
        raise e
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()
